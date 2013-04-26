<?php

/*
 * 
 * 
 * CORE FILE OF ADAPTIVE IMAGES (ÜBER-EXTENDED)
 * 
 * 
 * Adaptive Images (über-extendend) is forked from Adaptive Images (extended) by Johann Heyne
 * 
 * 		GitHub:		https://github.com/johannheyne/Adaptive-Images
 * 		Version:		1.5.2.1 (back then)
 * 
 * Adaptive Images (extendent) is forked from Adaptive Images by Matt Wilcox
 * 
 * 		GitHub:   	https://github.com/MattWilcox/Adaptive-Images
 * 		Homepage:		http://adaptive-images.com
 * 		Twitter:    	@responsiveimg
 * 		LEGAL:      	Adaptive Images by Matt Wilcox is licensed under a
 * 						Creative Commons Attribution 3.0 Unported License.
 * 		
 * 
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

 
     /* check that PHP has the GD library available to use for image re-sizing */
    if (!extension_loaded('gd')) { 						/* it's not loaded */
        if (!function_exists('dl') || !dl('gd.so')) { 	/* and we can't load it either */
            /* no GD available, so deliver the image straight up */
            trigger_error('You must enable the GD extension to make use of Adaptive Images', E_USER_WARNING);
            sendImage($source_file, $browser_cache);
        }
    }
 
 
 
 	// Fetch our outsourced settings and fill the variables below
    include('setup.php');

    $enable_resolutions = $config['enable_classicbehavior'];// The resolution break-points to use (screen widths, in pixels)
    $resolutions        = $config['resolutions']; 			// The resolution break-points to use (screen widths, in pixels)
    $breakpoints        = $config['breakpoints'];			// The image break-points to use in the src-parameter 
    $scalings		    = $config['scalings']; 				// NEW in AIue: the width of the generated images corresponting to the breakpoints
    $cache_path         = $config['cache_path']; 			// Where to store the generated re-sized images. Specify from your document root!
    $jpg_quality        = $config['jpg_quality']; 			// The quality of any generated JPGs on a scale of 0 to 100
    $jpg_quality_retina = $config['jpg_quality_retina']; 	// The quality of any generated JPGs on a scale of 0 to 100 for retina
    $sharpen            = $config['sharpen']['status']; 	// Shrinking images can blur details, perform a sharpen on re-scaled images?
    $sharpen_amount		= $config['sharpen']['amount'];		// Default amount of sharpening
    $sharpen_progressive= $config['sharpen']['progressive'];// If progressive sharpening is wanted
	$watch_cache        = $config['watch_cache']; 			// Check that the adapted image isn't stale (ensures updated source images are re-cached)
    $browser_cache      = $config['browser_cache']; 		// How long the BROWSER cache should last (seconds, minutes, hours, days. 7days by default)
    $debug_mode         = $config['debug_mode']; 			// Write new Image dimentions into the stored imageif(!$_GET['w']) $_GET['w'] = 100;
    $prevent_cache      = $config['prevent_cache']; 		// Always generate and deliver new images
    $setup_ratio_arr    = FALSE;							// Initializing crop ratio array variable for use afterwards
    $setup_ratio		= FALSE;							// Initializing crop ratio variable for use afterwards
    $fallback			= null;								// Triggers when fallback eventually becomes active
    $fallback_mobile	= $config['fallback']['mobile'];	// The default fallback resolution for mobile devices
    $fallback_desktop   = $config['fallback']['desktop'];	// The default fallback resolution for large/desktop displays	
    $fullsize_terms		= $config['fullsize_terms'];		// Assign the list of reserved terms to serve full size source image
	$wp					= $config['wordpress_detection'];	// Assign if wordpress detection is wantetd by the user
    
    // Get all of the required data from the HTTP request
    $document_root  = $_SERVER['DOCUMENT_ROOT'];
    $requested_uri  = parse_url(urldecode($_SERVER['REQUEST_URI']), PHP_URL_PATH);
    $requested_file = basename($requested_uri);
	$source_file    = $document_root.$requested_uri;
    $resolution     = FALSE;	
	
	
	
	// ###  Adaptive Images starts here  ###
	
	/* check if the requested file exists at all */
    if (!file_exists($source_file)) {
        header("Status: 404 Not Found");
        exit();
    	}
	
	

	// Wordpress detection
	if ($wp) {
	
		global $wp_width, $wp_height;
		
		// Take care of file name and stuff
		$source_file_name = pathinfo($source_file, PATHINFO_FILENAME);
		$source_file_ext  = pathinfo($source_file, PATHINFO_EXTENSION);
		
		// Have a look if a size string suffix is present
		// In the form of: {filename}-{width}x{height}
		$temp = explode('-', $source_file_name);
		$sizes = explode('x', array_pop($temp));

		// If yes: check if its usable
		if ( (count($sizes) === 2)
		 and (is_numeric($sizes[0])) 
		 and (is_numeric($sizes[1])) ) 
		 {
		 	// And exctract it for further clever things
			$wp_width  = (int) $sizes[0];
			$wp_height = (int) $sizes[1];
			
			// In case image is said to be square, set up cropping of new image
			if ( $wp_width == $wp_height ) $wp_setup_ratio = 1;
			
			// Just to be save
			$wp = true;
		 }
		// If not: cancel Wordpress thing with this image
		else { $wp = false; }
		
	}
	
	
	
	// Helper function to swap the suffixed file with the original file if needed
	function wp_swap_source($old_source_file) {
			
		$ext  = pathinfo($old_source_file, PATHINFO_EXTENSION);
		
		$name = pathinfo($old_source_file, PATHINFO_FILENAME);
		$name = explode('-', $name);
		$temp = array_pop($name);
		$name = implode('-', $name);
		
		$path = dirname($old_source_file) . '/';
		

		$new_source_file = $path . $name . '.' . $ext;
		
		// In case there cannot be found an unresized original file in Wordpress’ media directory
		// Fallback to original file requested
		// This happens when cropping was done manuall in Wordpress, e.g. on a thumbnail
		// Why? The filename changes and no information about how and where cropping took place is stored 
		if ( !file_exists($new_source_file) ) $new_source_file = $old_source_file;

		return $new_source_file;
	}



	// The 'shortcut' to full res image
	// When just the param is used, without any value
	// Test if this param is a reserved fullsize term 
	foreach ( $fullsize_terms as $fst) {
		if ( isset($_GET[$fst]) ) original_requested();
		}
	
	
	
	// Assess query string if one present with a size term
    if ( isset($_GET['size']) ) {
    	
		// Check query string whether to serve the source file
	 	// Reserved value for size param: 'original', 'full', 'fullsize', 'source', 'src'
	 	if ( in_array($_GET['size'], $fullsize_terms) ) original_requested();


		// When a valid size term is provided, use specific settings instead of the defaults and classics
        if ( isset($setup[$_GET['size']]['ratio'])) 				$setup_ratio_arr  = explode(':', $setup[$_GET['size']]['ratio']);
		if ( isset($setup[$_GET['size']]['sharpen']['status']) ) 	$sharpen = $setup[$_GET['size']]['sharpen']['status'];
        if ( isset($setup[$_GET['size']]['sharpen']['amount']) ) 	$sharpen_amount = $setup[$_GET['size']]['sharpen']['amount'];
		if ( isset($setup[$_GET['size']]['sharpen']['progressive']))$sharpen_progressive = $setup[$_GET['size']]['sharpen']['progressive'];
        if ( isset($setup[$_GET['size']]['jpg_quality']) ) 			$jpg_quality = $setup[$_GET['size']]['jpg_quality'];
        if ( isset($setup[$_GET['size']]['jpg_quality_retina']) ) 	$jpg_quality_retina = $setup[$_GET['size']]['jpg_quality_retina'];
		if ( isset($setup[$_GET['size']]['fallback']['mobile']) )  	$fallback_mobile = $setup[$_GET['size']]['fallback']['mobile'];
		if ( isset($setup[$_GET['size']]['fallback']['desktop']) ) 	$fallback_desktop = $setup[$_GET['size']]['fallback']['desktop']; 
    
    
        // Put image size values (scalings) of breakpoint names in an array
        foreach($setup[$_GET['size']]['breakpoints'] as $key => $value) {
            	
					if ($key == 'default') {
						$sizeterm_data[$key]['unit'] = 'px';
		                $sizeterm_data[$key]['val'] = $value;
						}
					
					$x = explode('%', $value );
		            if(count($x) === 2) {
		                $sizeterm_data[$key]['unit'] = '%';
		                $sizeterm_data[$key]['val'] = $x[0];
		            	}	
					
					$x = explode('px', $value );
		            if(count($x) === 2) {
		                $sizeterm_data[$key]['unit'] = 'px';
		                $sizeterm_data[$key]['val'] = $x[0];
		            	}	
					
					if ( $value == 'original' ) {
		                $sizeterm_data[$key]['unit'] = 'original';
		                $sizeterm_data[$key]['val'] = 'original';				
						}		
				}
      


		// Sanitize the ratio values, normalize, and reduce fraction to 1 digit maximum
		$setup_ratio_arr[0] = (float) str_replace( ',', '.', $setup_ratio_arr[0]);
		$setup_ratio_arr[1] = (float) str_replace( ',', '.', $setup_ratio_arr[1]);
		
		$setup_ratio_arr[0] = round( ($setup_ratio_arr[0] / $setup_ratio_arr[1]), 1, PHP_ROUND_HALF_UP);
		$setup_ratio_arr[1] = 1;	// practically useless; just for completeness
		
		$setup_ratio = $setup_ratio_arr[0];		// we only need to use this one now
		

}  /* End of query string assessment */

	
	
	
	// Helper function to handle request of original full size image
	function original_requested($wp_original = true) {
		global $source_file, $browser_cache, $wp;
		
		// In Wordpress detection mode, do REALLY serve the original file
		// Except when "classic behavior" is off, then serve the requested file
		if ($wp and $wp_original) $source_file = wp_swap_source($source_file);
		
		// Send the image
		sendImage($source_file, $browser_cache);
        die();
		}
	


	// Serve the source file, in case "classic behavior" is off and no size terms are provided    
    if( !$enable_resolutions and !$wp) {
        if( !isset($sizeterm_data) || count($sizeterm_data) === 0 ) { original_requested(); }
    	}

	

    /* Mobile detection 
    NOTE: only used in the event a cookie isn't available. */
    function is_mobile() {
        $userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);
        return strpos($userAgent, 'mobile');
    }

    // Does the UA string indicate this is a mobile?
	// Shortcut to achieve that; idea: commit b883be0 by nikcorg
	$is_mobile = is_mobile();



    /* does the $cache_path directory exist already? */
    if (!is_dir("$document_root/$cache_path")) { 					/* no */
        if (!mkdir("$document_root/$cache_path", 0755, true)) { 	/* so make it */
            if (!is_dir("$document_root/$cache_path")) { 			/* check again to protect against race conditions */
                /* uh-oh, failed to make that directory */
                sendErrorImage("Failed to create cache directory at: $document_root/$cache_path");
            }
        }
    }


    /* helper function: Send headers and returns an image. */
    function sendImage($filename, $browser_cache) {
        
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($extension, array('png', 'gif', 'jpeg'))) {
            header("Content-Type: image/".$extension);
        }
        else {
            header("Content-Type: image/jpeg");
        }
        header("Cache-Control: private, max-age=".$browser_cache);
        header('Expires: '.gmdate('D, d M Y H:i:s', time()+$browser_cache).' GMT');
        header('Content-Length: '.filesize($filename));
        readfile($filename);
        exit();
    }

    
	
    /* helper function: Create and send an image with an error message. */
    function sendErrorImage($message) {
        /* get all of the required data from the HTTP request */
        $document_root  = $_SERVER['DOCUMENT_ROOT'];
        $requested_uri  = parse_url(urldecode($_SERVER['REQUEST_URI']), PHP_URL_PATH);
        $requested_file = basename($requested_uri);
        $source_file    = $document_root.$requested_uri;

        if(!is_mobile()){
            $is_mobile = "FALSE";
        }
        else {
            $is_mobile = "TRUE";
        }

        $im            = ImageCreateTrueColor(800, 300);
        $text_color    = ImageColorAllocate($im, 233, 14, 91);
        $message_color = ImageColorAllocate($im, 91, 112, 233);

        ImageString($im, 5, 5, 5, "Adaptive Images encountered a problem:", $text_color);
        ImageString($im, 3, 5, 25, $message, $message_color);

        ImageString($im, 5, 5, 85, "Potentially useful information:", $text_color);
        ImageString($im, 3, 5, 105, "DOCUMENT ROOT IS: $document_root", $text_color);
        ImageString($im, 3, 5, 125, "REQUESTED URI WAS: $requested_uri", $text_color);
        ImageString($im, 3, 5, 145, "REQUESTED FILE WAS: $requested_file", $text_color);
        ImageString($im, 3, 5, 165, "SOURCE FILE IS: $source_file", $text_color);
        ImageString($im, 3, 5, 185, "DEVICE IS MOBILE? $is_mobile", $text_color);

        header("Cache-Control: no-store");
        header('Expires: '.gmdate('D, d M Y H:i:s', time()-1000).' GMT');
        header('Content-Type: image/jpeg');
        ImageJpeg($im);
        ImageDestroy($im);
        exit();
    }

    /* sharpen images function */
    function findSharp($intOrig, $intFinal) {
        $intFinal = $intFinal * (750.0 / $intOrig);
        $intA     = 52;
        $intB     = -0.27810650887573124;
        $intC     = .00047337278106508946;
        $intRes   = $intA + $intB * $intFinal + $intC * $intFinal * $intFinal;
        return max(round($intRes), 0);
    }

    /* refreshes the cached image if it's outdated */
    function refreshCache($source_file, $cache_file, $resolution) {

        /* prevents caching by config ($prevent_cache and $debug mode) */
        global $debug_mode;
        global $prevent_cache;
        if($prevent_cache) unlink($cache_file);

        if (file_exists($cache_file)) {
            /* not modified */
            if (filemtime($cache_file) >= filemtime($source_file)) {
                return $cache_file;
            }

            /* modified, clear it */
            unlink($cache_file);
        }
        return generateImage($source_file, $cache_file, $resolution);
    }

    
		 
	 
	 
	// ###################    M A I N   F U N C T I O N   #1   ################### //
	// Generates the given cache file for the given source file with the given resolution
    function generateImage($source_file, $cache_file, $resolution) {
    
        global $sharpen, $sharpen_amount, $sharpen_progressive;
		global $jpg_quality, $jpg_quality_retina;
		global $setup_ratio, $wp_setup_ratio;
        global $is_highppi;
		

		// Double-check, if path exists and is writable
        $cache_dir = dirname($cache_file);

        /* does the directory exist already? */
        if (!is_dir($cache_dir)) { 
            if (!mkdir($cache_dir, 0755, true)) {
                /* check again if it really doesn't exist to protect against race conditions */
                if (!is_dir($cache_dir)) {
                    sendErrorImage("Failed to create cache directory: $cache_dir");
                }
            }
        }

        if (!is_writable($cache_dir)) {
            sendErrorImage("The cache directory is not writable: $cache_dir");
        }
		
		
        $extension = strtolower(pathinfo($source_file, PATHINFO_EXTENSION));

        /* Check the image dimensions */
        $dimensions   = GetImageSize($source_file);
        $width        = $dimensions[0];
        $height       = $dimensions[1];
		

        /* Do we need to downscale the image? */
        /* because of cropping, we need to prozess the image
        if ($width <= $resolution) { // no, because the width of the source image is already less than the client width
            return $source_file;
        }
        */
        
        // General quality setting
        $jpg_quality_high = $jpg_quality;
		if ($is_highppi) $jpg_quality = $jpg_quality_retina;
		
		
		// TODO! ################
		// Some clever stuff to only scale and compress when necessary
		// Or myybe when to serve the original file
		
		// Do not compress additionaly, when image is already very small
		if ( $width <= $resolution and $is_highppi) $jpg_quality = $jpg_quality_high;	
		if ( $width <= 150 ) $jpg_quality = $jpg_quality_high;
		
		// Lower quality just a bit more, when image should become relatively huge
		if ( $width > 1200 and $resolution > 1200 and !$is_highppi) $jpg_quality = $jpg_quality - 15;
		if ( $width > 1200 and $is_highppi) $jpg_quality = $jpg_quality - 5;
		
		// In case no cropping needs to be done and no high-ppi compression, and no resizing at all, we can serve the source file
		if (!$setup_ratio and $width <= $resolution and !$is_highppi) return $source_file;
		if (!$wp_setup_ratio and $width <= $resolution and !$is_highppi) return $source_file;
		

		// Progressive Sharpening
		if ( $sharpen_progressive ) {
			$factor = $resolution / $width;
			$sharpen_amount = $sharpen_amount * ( 1 - pow($factor,3) );
			$sharpen_amount = ($sharpen_amount > 0) ? floor($sharpen_amount) : 0;
			}
		

    
        /* We need to resize the source image to the width of the resolution breakpoint we're working with */
        $ratio = $height / $width;
        if ($width <= $resolution) {
        		$new_width  = $width;
        }
        else {
            	$new_width  = $resolution;
        }
    
        $new_height = ceil($new_width * $ratio);
    
        $debug_width = $new_width;
        $debug_height = $new_height;
        
        $start_x = 0;
        $start_y = 0;
        
		
		// In case something about cropping is to be done
        if ( $setup_ratio or $wp_setup_ratio) {

            /* set height for new image */ 
            $orig_ratio = $new_width / $new_height;
            
			// Set crop ration, but priorize the one of the size terms
            $crop_ratio = ($setup_ratio) ? $setup_ratio : $wp_setup_ratio;
			
            $ratio_diff = $orig_ratio / $crop_ratio;
            $ini_new_height = ceil($new_height * $ratio_diff);
        
            $dst = ImageCreateTrueColor($new_width, $ini_new_height); /* re-sized image */
        
            $debug_width = $new_width;
            $debug_height = $ini_new_height;
        
            /* set new width and height for skaleing image to fit new height */
            
            if($ini_new_height > $new_height) {
                $crop_factor = $ini_new_height / $new_height;
                $temp_new_width = ceil($new_width * $crop_factor);
                $new_height = ceil($new_height * $crop_factor);
                $start_x = ($new_width - $temp_new_width) / 2;
                $new_width = $temp_new_width;
            }
            else {
                $start_y = -($new_height - $ini_new_height) / 2;
            }
        }
        else {
            $dst = ImageCreateTrueColor($new_width, $new_height); /* re-sized image */
        }
    
        switch ($extension) {
            case 'png':
            $src = @ImageCreateFromPng($source_file); 	/* original image */
            break;
            case 'gif':
            $src = @ImageCreateFromGif($source_file); 	/* original image */
            break;
            default:
            $src = @ImageCreateFromJpeg($source_file); 	/* original image */
            ImageInterlace($dst, true); 				/* Enable interlancing (progressive JPG, smaller size file) */
            break;
        }
        if($extension=='png') {
            imagealphablending($dst, false);
            imagesavealpha($dst,true);
            $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
            imagefilledrectangle($dst, 0, 0, $new_width, $new_height, $transparent);
        }
    
        ImageCopyResampled($dst, $src, $start_x, $start_y, 0, 0, $new_width, $new_height, $width, $height); /* do the resize in memory */
        
        /* debug mode */
        global $debug_mode;
		global $wp, $wp_width, $wp_height, $fallback;
		
        if($debug_mode) {
        	
			$color = imagecolorallocate($dst, 255, 0, 255); // Use fresh magenta
			
            // first debug line: write a textstring with dimensions etc.
            $cookie_data = explode(',', $_COOKIE['resolution']);
            $debug_ratio = false;
	     	if( $setup_ratio ) $debug_ratio = $setup_ratio . ':1';
            imagestring( $dst, 5, 10, 5, $debug_width." x ".$debug_height . ' ' . $debug_ratio . ' device:' . $cookie_data[0] . '*' . $cookie_data[1] . '=' . ceil($cookie_data[0] * $cookie_data[1]) . $addonstring, $color);
	     
	     	// second debug line: show size term if provided
	     	$secondline = $_GET['size'];
	     	imagestring( $dst, 5, 10, 20, $secondline, $color);

			// Third line: Wpordpress detection active
			if ( $wp ) $thirdline = "Wordpress detection is active!";
			imagestring( $dst, 5, 10, 35, $thirdline, $color);
			
			// Fourth debug line: is fallback active?
		 	if ( $fallback ) $fourthline = "Fallback active!";
		 	imagestring( $dst, 5, 10, 50, $fourthline, $color);
			
			$fifthline = "Sharpen Amount: " . $sharpen_amount;
			imagestring( $dst, 5, 10, 65, $fifthline, $color);
			
        }

        
        ImageDestroy($src);



        /* sharpen the image */
        if($sharpen == TRUE) {
            $amount = $sharpen_amount; /* max 500 */
            $radius = '1'; /* 50 */
            $threshold = '0'; /* max 255 */
            
            if ( strtolower($extension) == 'jpg' OR strtolower($extension) == 'jpeg') {
                if($amount !== '0') $dst = UnsharpMask($dst, $amount, $radius, $threshold);
            }
        }



        /* save the new file in the appropriate path, and send a version to the browser */
        switch ($extension) {
            case 'png':
            $gotSaved = ImagePng($dst, $cache_file);
            break;
            case 'gif':
            $gotSaved = ImageGif($dst, $cache_file);
            break;
            default:
            $gotSaved = ImageJpeg($dst, $cache_file, $jpg_quality);
            break;
        }
        ImageDestroy($dst);

        if (!$gotSaved && !file_exists($cache_file)) {
            sendErrorImage("Failed to create image: $cache_file");
        }

        return $cache_file;
		
    }  /* end of generateImage() */







    // ###################     M A I N   F U N C T I O N   #2    ################### //
	// The cookie detection and calculation of the image size
    if (isset($_COOKIE['resolution']) ) {
        $cookie_value = $_COOKIE['resolution'];
    
        /* does the cookie look valid? [whole number, comma, potential floating number] */
        if (! preg_match("/^[0-9]+[,]*[0-9\.]+$/", "$cookie_value")) { /* no it doesn't look valid */
            setcookie("resolution", $cookie_value, time()-99999, '/'); /* delete the mangled cookie */
        
			// serve at least the wordpress images, where size is given
			// TODO!
		
		}
        else {

			// If cookie is valid, do all the stuff with it
			// General preparations
            $cookie_data   = explode(",", $_COOKIE['resolution']);
            $client_width  = (int) $cookie_data[0]; /* the base resolution (CSS pixels) */
            $pixel_density = 1; /* set a default, used for non-retina style JS snippet */
            if ( @$cookie_data[1] ) $pixel_density = $cookie_data[1]; /* the device's pixel density factor (physical pixels per CSS pixel) */
			global $is_highppi;
			$is_highppi = ($pixel_density <= 1 ) ? false : $pixel_density;  
			
			
	        	// In case a size term is submitted via query string
	        	// Rewrite the breakpoints and scalings accordingly
	        	// Size term breakpoints become the new resolution breakpoints for use in "classic behavior" later
	        	if(isset($sizeterm_data)) {
	      				
						// Translate %-values of the visitors screen in px values 
			        	foreach($sizeterm_data as $key => $item) {

								if ( $item['unit'] === '%' )
								   { $sizeterm_data[$key]['unit'] = 'px';
									 $sizeterm_data[$key]['val'] = (int) ceil($client_width * $item['val'] / 100);
								   } 	
								}

						// Reset the resolutions and scalings array
						$resolutions = array();
						$scalings = array();
						$i = 0;		/* Awkard: I saw no other chance to do the filling, as with a counter ... */
						
						foreach ($breakpoints as $breakpoint => $value) {
	
							// Fill resolutions with widths of breakpoint default values from setup	
							$resolutions[$i] = $value;
							
							// Fill scalings (=resulting image sizes per breakpoint)
							// Use defined size values of size terms from setup
							if ( isset($sizeterm_data[$breakpoint]) ) {
								
								$scalings[$i] = (int) $sizeterm_data[$breakpoint]['val'];
							} 
							// If none is set at this position, use the one before again
							// (Or a fallback in some way?)
							else {
								 
								  $scalings[$i] = $scalings[$i-1]; }
							
							$i++;
							}

							if ($wp) $source_file = wp_swap_source($source_file);
							
					}  /* isset images_param */	
		
			  
			
	 		// If no size term is submitted or size term related actions are finished
			// Use original behavior and listen to the resolution breakpoints and scalings
			$resolutions = array_reverse($resolutions); /* make sure the supplied break-points are in reverse size order */
            $scalings = array_reverse($scalings);    /* same with scalings */
            $resolution = $scalings[0]; /* by default use the largest corresponding scaling size */
        
            /* if pixel density is not 1, then we need to be smart about adapting and fitting into the defined breakpoints */
            if($pixel_density > 1) {
            	
				// limit pixel density
				$pd_limit = 3;
				$pixel_density = ($pixel_density>$pd_limit) ? $pixel_density=$pd_limit : $pixel_density;
				
				
				// In case Classic Behavior is off and no Wordpress detection wanted
				// Do not make use of screen size and serve original file
				if (!$enable_resolutions and !$wp) original_requested(false);
				
				
				// Default: Make use of screen size and breakpoints
                foreach ($resolutions as $break_point) { /* filter down */
                    if ($client_width <= $break_point) {
                        // Use ['scalings']	
                        $key = array_search($break_point, $resolutions);
                        $resolution = $scalings[$key];
                        /*$resolution = $break_point;*/
			           }
                	}

					
				// If Wordpress detection is active: now is the time to do
				// But only if no size term were submitted
				if ($enable_resolutions and $wp and !$sizeterm_data) {
						
					// Tell different desired width: the width of pre-resized source image
					// But let it not become bigger than the screen size	
					if ( $resolution > $wp_width ) $resolution = $wp_width;
					
					// Tell a new source_file: the original in Wordpress media directory
					// We force to regernerate new high-ppi size from there	
					$source_file = wp_swap_source($source_file);
					}
				
					
				// If Classic Behavior is off, but Wordpress mode is on
				// Let’s just "re-retinize" the requested file	
				if (!$enable_resolutions and $wp and !$sizeterm_data) {
					$resolution = $wp_width;
					$source_file = wp_swap_source($source_file);			
					}	

				
			 // now apply the density multiplier
			 $resolution = $resolution * $pixel_density;
			
				
			
            }
            else { /* pixel density is 1, just fit it into one of the breakpoints */
            	/*$total_width = $client_width;*/
				
				// If Classic Mode is off: don’t make use of screen size
				if (!$enable_resolutions) { original_requested(false); }			
						
				
				// Default: make use of screen size and breakpoints								
                foreach ($resolutions as $break_point) { /* filter down */
                     if ($client_width <= $break_point) {
                    		// Yep: ['scalings']
                        	$key = array_search($break_point, $resolutions);
                            $resolution = $scalings[$key];                    	
                        	/*$resolution = $break_point;*/
					    	}		
		        	}
						
            	}
     				
		} /* end of valid cookie stuff */
} /* end of cookie detection */





    // FALLBACK
	// When no resolution was found due to no cookie or invalid cookie
    if (!$resolution) {
    	
        global $fallback, $fallback_mobile, $fallback_desktop;		

		// Use specific fallback resolutions from setup.php
		$fallback_mobile = (int) strtr($fallback_mobile, array ('px'=>'','%'=>''));
		$fallback_desktop = (int) strtr($fallback_desktop, array ('px'=>'','%'=>''));
		if ( $fallback_desktop <= $fallback_mobile ) $fallback_desktop = $fallback_mobile * 3;
        
        $resolution = $is_mobile ? $fallback_mobile : $fallback_desktop;
		$fallback = true;
		

    }
	
    
    /* if the requested URL starts with a slash, remove the slash */
    /*if(substr($requested_uri, 0,1) == "/") {
        $requested_uri = substr($requested_uri, 1);
    }*/
    
    // Trim any potential leading slashes; idea from commit a9c32c4 by nikcorg
	$requested_uri = ltrim($requested_uri, "/"); 
    

    // Whew might the cache file be?
    // Ratio slug now only one value; second is obsolete since normalizing!
    $ratio_slug = '';
	if ( $setup_ratio ) $ratio_slug = '-' . $setup_ratio;
	// Suppress ratio slug if Wordpress "silent cropping" was active
	if ( $wp_setup_ratio ) $ratio_slug = '';
	
	if ( $fallback ) $pixel_density = 1;
	$pixel_density_slug = '-' . $pixel_density;
     
    $cache_file = $document_root . "/" . $cache_path . "/" . $resolution . $pixel_density_slug . $ratio_slug . "/" . $requested_uri;
    
    
    
    /* Use the resolution value as a path variable and check to see if an image of the same name exists at that path */
    if (file_exists($cache_file)) { /* it exists cached at that size */
        if ($watch_cache) { /* if cache watching is enabled, compare cache and source modified dates to ensure the cache isn't stale */
            $cache_file = refreshCache($source_file, $cache_file, $resolution);
        }
        
        sendImage($cache_file, $browser_cache);
    }

    /* It exists as a source file, and it doesn't exist cached - lets make one: */
    $file = generateImage($source_file, $cache_file, $resolution);
    sendImage($file, $browser_cache);















// Additional sharpening function we don’t want to mess with

    function UnsharpMask($img, $amount, $radius, $threshold) {

        /*
            New:  
            - In version 2.1 (February 26 2007) Tom Bishop has done some important speed enhancements. 
            - From version 2 (July 17 2006) the script uses the imageconvolution function in PHP  
            version >= 5.1, which improves the performance considerably. 


            Unsharp masking is a traditional darkroom technique that has proven very suitable for  
            digital imaging. The principle of unsharp masking is to create a blurred copy of the image 
            and compare it to the underlying original. The difference in colour values 
            between the two images is greatest for the pixels near sharp edges. When this  
            difference is subtracted from the original image, the edges will be 
            accentuated.  

            The Amount parameter simply says how much of the effect you want. 100 is 'normal'. 
            Radius is the radius of the blurring circle of the mask. 'Threshold' is the least 
            difference in colour values that is allowed between the original and the mask. In practice 
            this means that low-contrast areas of the picture are left unrendered whereas edges 
            are treated normally. This is good for pictures of e.g. skin or blue skies. 

            Any suggenstions for improvement of the algorithm, expecially regarding the speed 
            and the roundoff errors in the Gaussian blur process, are welcome. 

        */

        ////////////////////////////////////////////////////////////////////////////////////////////////   
        ////   
        ////                  Unsharp Mask for PHP - version 2.1.1   
        ////   
        ////    Unsharp mask algorithm by Torstein Hønsi 2003-07.   
        ////             thoensi_at_netcom_dot_no.   
        ////               Please leave this notice.   
        ////   
        ///////////////////////////////////////////////////////////////////////////////////////////////   

        // $img is an image that is already created within php using  
        // imgcreatetruecolor. No url! $img must be a truecolor image.  

        // Attempt to calibrate the parameters to Photoshop:  
        if ($amount > 500)    $amount = 500;  
        $amount = $amount * 0.016;  
        if ($radius > 50)    $radius = 50;  
        $radius = $radius * 2;  
        if ($threshold > 255)    $threshold = 255;  

        $radius = abs(round($radius));     // Only integers make sense.  
        if ($radius == 0) {  
            return $img; imagedestroy($img); break;
        }  
        $w = imagesx($img); $h = imagesy($img);  
        $imgCanvas = imagecreatetruecolor($w, $h);  
        $imgBlur = imagecreatetruecolor($w, $h);  


        // Gaussian blur matrix:  
        //                          
        //    1    2    1          
        //    2    4    2          
        //    1    2    1          
        //                          
        //////////////////////////////////////////////////  


        if (function_exists('imageconvolution')) { // PHP >= 5.1   
            $matrix = array(   
            array( 1, 2, 1 ),   
            array( 2, 4, 2 ),   
            array( 1, 2, 1 )   
            );   
            imagecopy ($imgBlur, $img, 0, 0, 0, 0, $w, $h);  
            imageconvolution($imgBlur, $matrix, 16, 0);   
        }   
        else {   

            // Move copies of the image around one pixel at the time and merge them with weight  
            // according to the matrix. The same matrix is simply repeated for higher radii.  
            for ($i = 0; $i < $radius; $i++) {  
                imagecopy ($imgBlur, $img, 0, 0, 1, 0, $w - 1, $h); // left  
                imagecopymerge ($imgBlur, $img, 1, 0, 0, 0, $w, $h, 50); // right  
                imagecopymerge ($imgBlur, $img, 0, 0, 0, 0, $w, $h, 50); // center  
                imagecopy ($imgCanvas, $imgBlur, 0, 0, 0, 0, $w, $h);  

                imagecopymerge ($imgBlur, $imgCanvas, 0, 0, 0, 1, $w, $h - 1, 33.33333 ); // up  
                imagecopymerge ($imgBlur, $imgCanvas, 0, 1, 0, 0, $w, $h, 25); // down  
            }  
        }  

        if($threshold>0) {  
            // Calculate the difference between the blurred pixels and the original  
            // and set the pixels  
            for ($x = 0; $x < $w-1; $x++)    { // each row 
                for ($y = 0; $y < $h; $y++)    { // each pixel  

                    $rgbOrig = ImageColorAt($img, $x, $y);  
                    $rOrig = (($rgbOrig >> 16) & 0xFF);  
                    $gOrig = (($rgbOrig >> 8) & 0xFF);  
                    $bOrig = ($rgbOrig & 0xFF);  

                    $rgbBlur = ImageColorAt($imgBlur, $x, $y);  

                    $rBlur = (($rgbBlur >> 16) & 0xFF);  
                    $gBlur = (($rgbBlur >> 8) & 0xFF);  
                    $bBlur = ($rgbBlur & 0xFF);  

                    // When the masked pixels differ less from the original  
                    // than the threshold specifies, they are set to their original value.  
                    $rNew = (abs($rOrig - $rBlur) >= $threshold)   
                    ? max(0, min(255, ($amount * ($rOrig - $rBlur)) + $rOrig))   
                    : $rOrig;  
                    $gNew = (abs($gOrig - $gBlur) >= $threshold)   
                    ? max(0, min(255, ($amount * ($gOrig - $gBlur)) + $gOrig))   
                    : $gOrig;  
                    $bNew = (abs($bOrig - $bBlur) >= $threshold)   
                    ? max(0, min(255, ($amount * ($bOrig - $bBlur)) + $bOrig))   
                    : $bOrig;  



                    if (($rOrig != $rNew) || ($gOrig != $gNew) || ($bOrig != $bNew)) {  
                        $pixCol = ImageColorAllocate($img, $rNew, $gNew, $bNew);  
                        ImageSetPixel($img, $x, $y, $pixCol);  
                    }  
                }  
            }  
        }  
        else {  
            for ($x = 0; $x < $w; $x++) { // each row  
                for ($y = 0; $y < $h; $y++) { // each pixel  
                    $rgbOrig = ImageColorAt($img, $x, $y);  
                    $rOrig = (($rgbOrig >> 16) & 0xFF);  
                    $gOrig = (($rgbOrig >> 8) & 0xFF);  
                    $bOrig = ($rgbOrig & 0xFF);  

                    $rgbBlur = ImageColorAt($imgBlur, $x, $y);  

                    $rBlur = (($rgbBlur >> 16) & 0xFF);  
                    $gBlur = (($rgbBlur >> 8) & 0xFF);  
                    $bBlur = ($rgbBlur & 0xFF);  

                    $rNew = ($amount * ($rOrig - $rBlur)) + $rOrig;  
                    if($rNew>255){$rNew=255;}  
                    elseif($rNew<0){$rNew=0;}  
                    $gNew = ($amount * ($gOrig - $gBlur)) + $gOrig;  
                    if($gNew>255){$gNew=255;}  
                    elseif($gNew<0){$gNew=0;}  
                    $bNew = ($amount * ($bOrig - $bBlur)) + $bOrig;  
                    if($bNew>255){$bNew=255;}  
                    elseif($bNew<0){$bNew=0;}  
                    $rgbNew = ($rNew << 16) + ($gNew <<8) + $bNew;  
                    ImageSetPixel($img, $x, $y, $rgbNew);  
                }  
            }  
        }  
        imagedestroy($imgCanvas);  
        imagedestroy($imgBlur);  

        return $img;
    }

    
    
    
    
    
    
// Litte helper function for debugging	
function v($variable) {	die( var_dump($variable) ); }	
    