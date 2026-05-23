<?php
/**
 * Handles the templating part.
 * 
 * All templates are cached, within a _cached/ subfolder within the templates/ folder.
 * 
 * Templates are handled with the handler function, 
 * which subsequently calls the builder method, 
 * which in turn, actually builds these templates, 
 * and puts them in this cached folder.
 * 
 * Whether the cache is up to date is determined by the file modification date.
 * 
 */
class Template {

static function handle($template, $data) {
	
	// 1. Check if we have a cached version in /templates/_cache/
	$templatePath = ROOT . '/templates/'        . $template . '.html';
	$cachePath    = ROOT . '/templates/_cache/' . $template . '.php';
	
	// 2. Check if we have a cached version
	$templateExists = file_exists($templatePath);
	$cacheExists    = file_exists($cachePath);
	
	// 3. Handle error
	if(!$templateExists) {
		throw new Exception('Template does not exist: ' . $templatePath);
	}
	
	// 4. Check if we have to rebuild the template
	$cacheUp2Date = false;
	if($cacheExists) {
		$templateTime = filemtime($templatePath);
		$cacheTime    = filemtime($cachePath);
		
		$cacheUp2Date = $templateTime < $cacheTime;
	}
	
	// <DEV> Force false for now, because we are still working on the template building
	$cacheUp2Date = false;
	
	// 5. Rebuild the template
	if(!$cacheUp2Date) {
		self::build($templatePath, $cachePath);
	}
	
	// 6. At this point, we have a cached version of the template
	//    because it exists, and it is up to date, so we can include it
	include($cachePath);
	
	// 7. Call the template function
	ob_start();
	template($data);
	$result = ob_get_clean();
	
	// 8. Return the output text
	return $result;
}


/**
* Build the template into a PHP function
* 
* A template file can contain the following syntax.
* 
* 
* Printing:
* 
* - {{$var}}   =>  <?php echo $var; ?>
* - {{{$var}}} =>  <?php echo htmlspecialchars($var); ?>
* - {dump #}   =>  <?php var_dump(#); ?>
* - {export #} =>  <?php var_export(#); ?>
* 
* Blocks
* 
* - {if #}     =>  <?php if(#) { ?>
* - {else}     =>  <?php } else { ?>
* - {each #}   =>  <?php foreach (#) { ?>
* 
* - {start}    =>  <?php { ?>
* - {end}      =>  <?php } ?>
* - {stop}     =>  <?php return; ?>
* - {break}    =>  <?php break; ?>
* - {breakblock}  => <?php do { ?>
* - {/breakblock} => <?php } while(false); ?>
* 
* General PHP
* 
* - `#` => <?php # ?> : execute PHP code
* 
* 
* Base Template / Copy / Pastes
* 
* - {extend $template} => $extend_contents = file_get_contents($template);
* - {cut $blockName}CONTENT{/cut} => injected into {% paste $block %}
* - {paste $blockName} => paste whatever was in %cut $block%
* - {main}             => defined in extended template, replaced by child template contents
* 
* 
* Other
* 
* - {debug}  => <pre style="background: black; color: white;">
* - {/debug} => </pre>
* 
* - /* # */ /* => Will collapse into nothing   */
/**/
static function build($templatePath, $cachePath) {
	
	// 1. Read the template file
	$templateContent = file_get_contents($templatePath);
	
	// 2. Check for {extend} block, if it exists. Load it,
	//    put the contents of the extended template in a variable
	if(preg_match('/\{\s*extend\s+(.+?)\s*\}/s', $templateContent, $matches)) {
		$extendPath = ROOT . '/templates/' . $matches[1] . '.html';
		$extendContent = file_get_contents($extendPath);
		
		// Remove the extend block from the template
		$templateContent = str_replace($matches[0], '', $templateContent);
		
		// Replace the {main} block in the extended template with the template content
		// We use a unique placeholder then str_replace to avoid preg_replace backreference issues with $ or \
		$extendContent = preg_replace('/\{\s*main\s*\}/s', '___TEMPLATE_MAIN_BODY_PLACEHOLDER___', $extendContent);
		$templateContent = str_replace('___TEMPLATE_MAIN_BODY_PLACEHOLDER___', trim($templateContent), $extendContent);
	}

	// Handle {include} blocks
	// This allows pasting the content of another template file into the current one.
	while (preg_match('/\{\s*include\s+(.+?)\s*\}/s', $templateContent, $matches)) {
		$includePath = ROOT . '/templates/' . trim($matches[1]);
		$includeContent = '';
		if (file_exists($includePath)) {
			$includeContent = file_get_contents($includePath);
		}
		$templateContent = str_replace($matches[0], $includeContent, $templateContent);
	}

	// Handle {use} and {usedef} blocks
	// This tracks which components are required and strips unused definitions.
	$usedNames = [];
	if (preg_match_all('/\{\s*use\s+(.+?)\s*\}/s', $templateContent, $useMatches)) {
		foreach ($useMatches[1] as $match) {
			$names = explode(',', $match);
			foreach ($names as $name) {
				$usedNames[] = trim($name);
			}
		}
		$templateContent = preg_replace('/\{\s*use\s+.+?\s*\}/s', '', $templateContent);
	}
	$usedNames = array_unique($usedNames);
	$templateContent = preg_replace_callback('/\{\s*usedef\s+([a-zA-Z0-9_-]+)\s*\}(.*?)\{\s*\/usedef\s*\}/s', function($m) use ($usedNames) {
		return in_array($m[1], $usedNames) ? $m[2] : '';
	}, $templateContent);

	// 3. Handle {raw} blocks
	//    We do this AFTER extension so we catch {raw} tags in the base template too.
	$rawBuffers = [];
	$templateContent = preg_replace_callback('/\{\s*raw\s*\}(.*?)\{\s*\/raw\s*\}/s', function($matches) use (&$rawBuffers) {
		$index = count($rawBuffers);
		$rawBuffers[] = $matches[1];
		return "{__RAW_BUFFER_{$index}__}";
	}, $templateContent);
	
	// 4. Handle {cut} and {paste} Blocks
	//    loop through every cut block, and find a matching paste block, 
	//    and replace the paste blocks with the content of the cut blocks.
	//    remove the cut blocks from the template
	//    
	//    We do this after the extends, because paste blocks can be defined within the extends, 
	//    where cut is defined within the sub template.
	{
		// Look through the template for cut blocks
		$cutBlocks = [];
		preg_match_all('/\{\s*cut\s+(.+?)\s*\}(.+?)\{\s*\/cut\s*\}/s', $templateContent, $cutBlocks, PREG_SET_ORDER);
		
		// Now we have an array of cut blocks, where each sub array contains 3 elements:
		//   - the entire block
		//   - the block name
		//   - the block content
		// 
		// Now we have to loop through this array, and for each cut block: 
		//   1. find a matching paste block`
		//   2. replace the paste block with the cut block content
		//   
		foreach($cutBlocks as $cutBlock) {
			// now we have to find a matching paste block, which contains the same block name
			$blockName = $cutBlock[1];
			$blockContent = $cutBlock[2];
			
			// look through the template for a paste block with the same name
			$regex = '/\{\s*paste\s+' . $blockName . '\s*\}/s';
			$match = null;
			preg_match($regex, $templateContent, $match);
			
			// if we have a match, then replace the paste block with the block content
			if($match) {
				$templateContent = str_replace($match[0], trim($blockContent), $templateContent);
			}
		}
		
		// After we've put all of the cut content within paste, 
		//   1. we have to remove all cut blocks from the template.
		//   2. also remove any trailing enter characters after cut blocks
		$templateContent = preg_replace('/\{\s*cut\s+.+?\s*\}.+?\{\s*\/cut\s*\}(\r|\n)*/s', '', $templateContent);
		//   3. Remove any non filled {paste} blocks
		$templateContent = preg_replace('/\{\s*paste\s+.+?\s*\}/s', '', $templateContent);
	}
	
	// 5. Handle the {{{ $var }}} syntax
	$templateContent = preg_replace('/\{\{\{\s*(.+?)\s*\}\}\}/s', '<?php echo htmlspecialchars($1); ?>', $templateContent);
	
	
	// 6. Handle the {{ $var }} syntax
	$templateContent = preg_replace('/\{\{\s*(.+?)\s*\}\}/s', '<?php echo $1; ?>', $templateContent);
	
	
	// 7. Handle the {dump #} syntax
	$templateContent = preg_replace('/\{\s*dump\s+(.+?)\s*\}/s', '<?php var_dump($1); ?>', $templateContent);
	
	
	// 8. Handle the {export #} syntax
	$templateContent = preg_replace('/\{\s*export\s+(.+?)\s*\}/s', '<?php var_export($1); ?>', $templateContent);
	
	
	// 9. Handle the {if #} syntax
	$templateContent = preg_replace('/\{\s*if\s+(.+?)\s*\}/s', '<?php if($1) { ?>', $templateContent);
	
	
	// 10. Handle the {else} syntax
	$templateContent = preg_replace('/\{\s*else\s*\}/s', '<?php } else { ?>', $templateContent);
	
	
	// 11. Handle the {each #} syntax
	$templateContent = preg_replace('/\{\s*each\s+(.+?)\s*\}/s', '<?php foreach($1) { ?>', $templateContent);
	
	
	// 12. Handle the {start} syntax
	$templateContent = preg_replace('/\{\s*start\s*\}/s', '<?php { ?>', $templateContent);
	
	
	// 13. Handle the {end} syntax
	$templateContent = preg_replace('/\{\s*end\s*\}/s', '<?php } ?>', $templateContent);
	
	// 14. Handle {stop} syntax
	$templateContent = preg_replace('/\{\s*stop\s*\}/s', '<?php return; ?>', $templateContent);
	
	// 15. Handle {break} syntax
	$templateContent = preg_replace('/\{\s*break\s*\}/s', '<?php break; ?>', $templateContent);
	
	// 16. Handle {breakblock} and {/breakblock} syntax
	$templateContent = preg_replace('/\{\s*breakblock\s*\}/s', '<?php do { ?>', $templateContent);
	$templateContent = preg_replace('/\{\s*\/breakblock\s*\}/s', '<?php } while(false); ?>', $templateContent);
	
	// 17. Handle `#` syntax
	$templateContent = preg_replace('/`\s*(.+?)\s*`/s', '<?php $1 ?>', $templateContent);
	
	
	// 18. Handle {debug} and {/debug} syntax
	$templateContent = preg_replace('/\{\s*debug\s*\}/s', '<pre style="background:black; color:white; padding:4px 6px;">', $templateContent);
	$templateContent = preg_replace('/\{\s*\/debug\s*\}/s', '</pre>', $templateContent);
	
	// 19. Handle the /* # */ syntax
	$templateContent = preg_replace('/\/\*.+?\*\//s', '', $templateContent);
	
	// 20 Restore the {raw} blocks
	//      Now that all other transformations are done, we can put the 
	//      raw content back.
	foreach ($rawBuffers as $index => $content) {
		$templateContent = str_replace("{__RAW_BUFFER_{$index}__}", $content, $templateContent);
	}
	
	
	// After the transformations are over:
	
	// 21. Wrap it in a function
	$templateContent = '<?php function template($data) { extract($data); ?>' . $templateContent . '<?php } ?>';
	
	// 22. Write it out to the cache path
	is_dir(dirname($cachePath)) || mkdir(dirname($cachePath), 0777, true);
	file_put_contents($cachePath, $templateContent);
}

}