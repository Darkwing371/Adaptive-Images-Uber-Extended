<?php
 
/*    ##    R E A D M E   N O T E S   A N D   H O W   T O   S E T U P    ##
 *
 *
 * *Breakpoints and sizes*
 * Now you can define, what size an image has to have on specific configurable
 * design breakpoints equal to your CSS media queries. Your can give them
 * custom names (the "size term") and set the their widths in pixel or percentage.
 * If you use for instance 100% as a default case, the image will be 100% of the
 * size of the resolution, that is equal or closest higher than the present users
 * device maximum width – but maximum width of the largest configured resolution
 * breakpoint but also not larger than the original full-res image size.
 * 
 * 
 * *Cropping (cutting to fit)*
 * You can define an aspect ratio for a term. All depending images will be cropped
 * to that ratio after resizing is done.
 * 
 * 
 * *Sharpen*
 * You can define if you want to sharpen the resized images and even the amount of
 * sharpness applied. 0 is no sharpening at all, up to 30 is subtile, more than 30 is
 * really impacting, max is 500 (imagine that!). Usually doing this is fine,
 * but you may want to turn it off if your server is very very busy.
 * 
 * *JPEG quality*
 * You can define the JPEG quality for a size term when device pixel ratio is at 1.
 * If above 1 (= "retina"), you can set a different JPEG compression level – very useful
 * for applying "Netvlies Retina Revolution Trick"
 * (http://blog.netvlies.nl/design-interactie/retina-revolution/) if you want to use it.
 * JPEG quality 100: of course is best; down to 70: is pleasant;
 * below 40: really compressed (and small in size).
 * 
 * 
 * *Prevent cache*
 * For developing, you might want to disable caching, to see changes of the
 * setup and the images on  * every page reload. For this, setup the following
 * in the Main Settings section:
 * 
 * $config['browser_cache'] = 0;
 * $config['prevent_cache'] = TRUE;
 * 
 * But remember to turn caching on again, when the site is going live!
 * 
 * 
 * *Debug mode*
 * This inserts some information into the generated images like: image dimensions,
 * the aspect ratio, the device-width and pixel density.
 * 
 * Turn this off when site goes live!
 * 
 * 
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */





	// MAIN SETTINGS
	// where to store the generated image files; specify from your DOCUMENT_ROOT!
	// Make sure the folders have at least 755 permission!
	// If you don't like the cached images being written to that folder, you can put it somewhere else.
	// Just put the path to the folder here and make sure you create it on the server
	// if for some reason that couldn't be done automatically by adaptive-images.php.
	$config['cache_path']           = 'adaptive-images/ai-cache';
	
	// checks if the adapted image in cache aren’t outdated; ensures updated source images are cached again.
	// In case your server gets very busy, it may help performance to turn this to FALSE.
	// It will mean however, that you will have to manually clear out the cache directory if you change a resource file	
	$config['watch_cache']          = TRUE;			// default: true
	
	// other caching settings
	$config['browser_cache']        = 60 * 60 * 24;	// period of time in seconds the images will stay in cache of browsers	
	$config['browser_cache']		= 0;			// enable this line line only during developement!
	$config['prevent_cache']        = TRUE; 		// default: false; true: images will resized on every image request

	// while developing: inserts information like image dimensions, ratio and the device-width into the image
	$config['debug_mode']           = false;			// default: false



 
	// CLASSIC BEHAVIOR – Matts original device resolution depending solution
	// true: to have this original automatic resizer enabled; this is the default
	// false: to only use the size terms in a query string and serve the original full res pic at a "native request"
	$config['enable_classicbehavior']   = TRUE;  

	// Here are our breakpoints for the default behavior; screen widths in pixels; ascending order
	$config['resolutions']          = array(0, 320, 480, 640, 1080, 1440, 2160, 2880);

	// Now new in AIue: kind of 'one size fits all' values, corresponding to the resolutions above
	// These image widths are served, when a specific resolution is present
	$config['scalings']             = array(0, 320, 480, 640,  960, 1440, 1920, 2880); 



	// In addition to classic behavior, you can now:
	// Enable detection of Wordpress’ size suffix string in image file names
	// If one present: recalculate the needed size according to the pixel density
	// This way, you’re able to "re-retinize" your images
	// You can use this manually too, by naming images for instance: {thename}-{100x150}.{ext} 
	$config['wordpress_detection']	=	TRUE;		// default: false
	
	
	
	// This is part of Johanns extended version introducing the "size terms"
	// Configure new breakpoints here; with names and width values
	// These values should be equal to the resolutions above, to not confuse things more than necessary
	$config['breakpoints'] = array(
	                                 'default' =>    0,
	                                 'micro'   =>  320,
	                                 'mini'    =>  480,
	                                 'small'   =>  640,
	                                 'medium'  => 1080,
	                                 'normal'  => 1440,
	                                 'large'   => 2160,
	                                 'huge'    => 2880
	                              );
							
	// Check if the array sizes are equal, because this is very important!
	// If you receive the error message, align the sizes of the three arrays above!
	$line = __LINE__; 	$line = $line - 1;
	$error_str = "Error in Adaptive Images: all array sizes MUST be equal to work correctly!<br>
			      Check " . __FILE__ . " at line " . $line . " to solve this.";	 
	//if ( count($config['breakpoints']) != count($config['scalings']) or (count($config['resolutions']) != count($config['scalings'])) ) { exit($error_str); }
	
	
	// Setting the two fallback widths for mobile and desktop
	// can be overridden by size terms later
	// MUST be pixel or unitless (meaning pixel)
	$config['fallback']['mobile']  =  '480px';
	$config['fallback']['desktop'] = '1280px';
	
							
	// Some settings concerning the default behavior image quality
	$config['jpg_quality']          	= 97;			// quality of a generated JPG at device pixel ratio of 1; values: 0 to 100; you want set this to 100 (at least 98) to get good quality with GD lin
	$config['jpg_quality_retina']   	= 92;			// use this for netvlies' retina compression trick; values: 0 to 100; no need to go too low, slighty lower is sufficent for quality/size trade-off
	$config['jpg_quality_progressive']	= TRUE;			// adjusts quality according to resulting image size; smaller images (<320): higher quality, bigger images (>1200): lower quality
	$config['sharpen']['status']    	= TRUE;			// enables sharpening of resized images
	$config['sharpen']['amount']    	= 20;			// 0 is none, 30 is pleasant, max is 500; don’t overdo it (>50), it dramatically lowers the jpg quality and increases filesize
	$config['sharpen']['progressive']	= TRUE;			// Recalculates sharpening amount according to image shrinking amount (cubic curve)
	
	
	// register terms to reserve for serving the full size source image in any case
	$config['fullsize_terms']		= array(
											'original',
											'full',
											'fullsize',
											'source',
											'src'
											 );
	
	
	// EXTENDED BEHAVIOR
	// Define the size terms: these are the query strings used to append on the image file
	// If you set points wisely, you can save a lot of diskspace – since not every intermediate size gets cached on the disk
	// NOTE: 'original', 'full', 'fullsize', 'source' and 'src' are reserved terms!
	// They’re hardcoded to serve the original source image in any case!
	// We would need that, while having $config['enable_resolutions'] = TRUE; Matts original behavior
	// Below this is just an example how to set it up
	// You MUST use units like % or px! Otherwise it gets ignored and results in '100%'
	// Usage afterwards: <img src="image.jpg?size=term" />
	$setup['term']['breakpoints']['default'] = '0'; // means, that the source file is served. better to specify a px value here!
	$setup['term']['breakpoints']['normal'] = '960';
	$setup['term']['breakpoints']['huge'] = '1440';
	//$setup['term']['ratio'] = '1.6181:1';
	$setup['term']['jpg_quality'] = 95;
	$setup['term']['jpg_quality_retina'] = 45;
	$setup['term']['sharpen']['amount'] = 40;
	$setup['term']['fallback']['mobile'] = '320px';		// must be pixel!
	$setup['term']['fallback']['desktop'] = '1024px';	// must be pixel!

	
	
	// ###   set up own size terms here   ###
	
	// zoom size term: when image needs to be displayed in a lightbox or something
	$setup['zoom']['breakpoints']['default']	= '0';
	$setup['zoom']['breakpoints']['micro']		= '300%';
	$setup['zoom']['breakpoints']['mini'] 		= '200%';
	$setup['zoom']['breakpoints']['small'] 		= '150%';
	$setup['zoom']['breakpoints']['medium']		= '100%';
	$setup['zoom']['breakpoints']['normal'] 	= '100%';
	$setup['zoom']['breakpoints']['large'] 		= '100%';
	$setup['zoom']['breakpoints']['huge'] 		= '100%';
	$setup['zoom']['jpg_quality'] 				= 98;
	$setup['zoom']['jpg_quality_retina'] 		= 95;
	$setup['jpg_quality_progressive']			= false;
	$setup['zoom']['sharpen']['status'] 		= TRUE;
	$setup['zoom']['sharpen']['amount'] 		= 15;
	$setup['zoom']['sharpen']['progressive'] 	= TRUE;
	$setup['zoom']['fallback']['mobile'] 		= '640px';		// must be pixel
	$setup['zoom']['fallback']['desktop'] 		= '1440px';		// must be pixel

	


	// UBER-EXTENDED BEHAVIOR
	// Prepare percentage breakpoints as a built-in default to have it present
	// Maybe useful for fluid layouts
	// A smart amout of sharpening is applied, according to the amount of resizing done
	// NOTE: use carefully! This percentage things spams your ai-cache directory with all kinds of calculated resolutions!
	for ($percent = 1; $percent < 100; $percent++) {
		$setup[$percent . '%']['breakpoints']['default'] = $percent . '%';
		$setup[$percent . '%']['sharpen']['amount'] = floor( (100 - $percent) / 1.3); // slightly sharpen according to size   
		$setup[$percent . '%']['sharpen']['progressive'] = FALSE;
		}
	
	
	
	
?>