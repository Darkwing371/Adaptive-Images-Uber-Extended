// Original by Matt Wilcox; (included for legacy reasons)
// document.cookie='resolution='+Math.max(screen.width,screen.height)+'; path=/';

// Original by Matt Wilcox, introducing device pixel ratio; (it is our line of choice)
document.cookie = 'resolution=' + Math.max( screen.width, screen.height ) + ( "devicePixelRatio" in window ? "," + devicePixelRatio : ",1") + '; path=/';

// Enable this just for testing purposes! Use it to fake device width and pixel density ratio.
// document.cookie = 'resolution=1024,1' + '; path=/';