Merely adding a directory like this with subdirectories to your vendor directory, will
give your website a favicon. Adding a favicon used to be a simple matter but nowadays
there are many more browsers and use-cases relying on a wide range of formats of your
favicon to be available. Luckily, realfavicongenerator.net solves most of these problems.
I have no affiliation with them; just really like their (free) service.

To make the current vendor plugin work well and to customize your favicon, take the following 10 steps:

 1. Make a large image, at least 260x260 pixels, with your favicon. Make sure it still looks good
    when scaled way down to 16x16 pixels.

 2. If your website is called example.com and your vendor directory is located at
    example.com/mysite/vendor/ then the path to your favicon will be:

        /mysite/vendor/site_favicon/content/favicon

 3. Go to https://realfavicongenerator.net/ and press 'Select your Favicon Picture'. 

 4. Upload your picture and wait for the next page to appear.

 5. Scroll way down and where it says 'I cannot or I do not want to place favicon files at the 
    root of my web site. Instead I will place them here:' insert the path from Step 2.

 6. Play with the other settings.

 7. Generate your favicon; wait for the page with 'Install your favicon' to appear.

 8. Follow Steps 1 and 2 on the 'Install your favicon' page.

 9. Copy the code in Step 3 to the site_favicon/content/config.txt, replacing the lines already
    in there. It should look something like:

        metatags:

        <link rel="apple-touch-icon" sizes="180x180" href="/mysite/vendor/site_favicon/content/favicon/apple-touch-icon.png">
        <link rel="icon" type="image/png" sizes="32x32" href="/mysite/vendor/site_favicon/content/favicon/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/mysite/vendor/site_favicon/content/favicon/favicon-16x16.png">
        <link rel="manifest" href="/mysite/vendor/site_favicon/content/favicon/site.webmanifest">
        <link rel="mask-icon" href="/mysite/vendor/site_favicon/content/favicon/safari-pinned-tab.svg" color="#5bbad5">
        <link rel="shortcut icon" href="/mysite/vendor/site_favicon/content/favicon/favicon.ico">
        <meta name="msapplication-TileColor" content="#da532c">
        <meta name="msapplication-config" content="/mysite/vendor/site_favicon/content/favicon/browserconfig.xml">
        <meta name="theme-color" content="#ffffff">

 10. Check out your new favicon!

Note, that even though you did not add any files to your content directory or root, it still works. You can turn the favicon
off by prefixing an underscore: _site_favicon. It is then ignored completely. (Browsers tend to aggresively cache favicons so
you may still see the old one for a long time.)


