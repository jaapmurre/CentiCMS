# CentiCMS: 1% size, incredible power

Tiny flat-file content-management system in PHP, treating a website as much as possible as a JavaScript app.

## Features

 - Very small footprint: Smallest install is one PHP file (zipped this is only 13 kB)!
 - Flat-file approach, so no database necessary
 - Grow organically by adding more files as needed
 - Runs on any server that has PHP, i.e., on almost all affordable hosting providers
 - Specify everything by editing flat text files; there is an optional admin panel as well
 - Layout is handled with popular, well-documented libraries: Handlebars and Bootstrap
 - "Batteries are included" (but can all be taken out or swapped...): Bootstrap 4, Bootswatch, Shards, Fontawesome Icons, Material Icons,
   jQuery, Handlebars, Markdown, Moment, MathJax, ... 
 - Bootstrap-based means it works well on phones too
 - Multi-language capacities are built-in at a deep level, including multi-language images and files, url segments, captions, 
   pages, templates, pretyy much anything you can think of is multi-language enabled. There are also translation files and helpers.
 - Heavily uses CDNs, which ensures a tiny footprint and a fast website
 - Easily switch themes (23 built-in open-source themes!)
 - Or 'skin' an existing theme not already included and use that
 - Math (with MathJax) can be displayed if `mathjax: true` is set in the config.txt file:
   When \\(a \ne 0\\), there are two solutions to \\(ax^2 + bx + c = 0\\) and they are
   $$x = {-b \pm \sqrt{b^2-4ac} \over 2a}.$$
 - Third-party plugins are placed in the vendor directory and are automatically included, unless deactivated by prefixing the directory name 
   with an underscore.
   Third-party files can mostly be overridden in your own files, which allows fine-grained control over the behavior of third-party plugins.
 - Third-party files many include anything, from a simple Javascript library, Handlebars helper or partial template to complete site solutions.
 - You can switch frameworks if you insist, if you don't like Handlebars, Bootstrap, Font-Awesome, ...
 - Caching can optionally be turned on to speed up large web sites


## Limitations

 - You must be able to copy some files to the server
 - Because CentiCMS relies on Javascript for rendering pages, your site will only be visible if Javascript is activated in 
the browser. Certain browsers, like Tor, turn off Javascript by default but their use is rare and it is always
possible to turn Javascript on, even in Tor. Javascript is always available and all *modern* browers will be able to see your
site correctly.

## Philosophy

 - Use PHP only when necessary, e.g., to built the structure of your sitemap
 - Use mainly the most popular libraries for each purpose:  
     - Use Markdown to write text (or use editor in optional admin panel)
     - Handlebars as a default templating language
     - Bootstrap for style and layout
     - Over twenty (free) Bootstrap themes as easily configurable parameters (from Bootswatch, Shards)
     - Javascript for behavior that is not already included
     - PHP if you *must* do server-side things like manage data or users
 - Support multi-language sites at a deep level:
     - Everything is multi-language by default; multi-language can also be completely ignored in case of monolingual sites
 - Think of a website as an app:
     - Include all data necessary to display the website with the first accessed webpage and make this data accessible to Javascript 
     - Limit subsequent XHR (AJAX) calls to an absolute minimum: Keep using the data in the website app
 - Tiny, tiny footprint and extremely small and easy install:
     - Prefer libraries with CDNs where possible and use these to keep a very small install footprint
     - Don't use a database for basic site use, only flat text files with a transparent structure that can easily be modified
       in an editor
     - Put all the behavior and services of CentiCMS in a single index.php file in the root
 - Clear division between CentiCMS files, your files, and third-party files:
     - Vendor (third-party) files offer additional beahvior, like extensive admin dashboards, user admin, or webstore facilities
     - Keep vendor files completely separated and allow overrides of their behavior and appearance 

