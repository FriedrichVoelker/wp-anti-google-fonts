<?php

/**
 * Plugin Name: Anti Google Fonts
 * Description: Remove Google Fonts from your WordPress site and host them locally.
 * Version: 1.0.0
 * Author: Friedrich Völker
 * Author URI: https://völker.dev
 * License: GPL v2 or later
 */


// if folders do not exist, create it
if (!file_exists(plugin_dir_path(__FILE__) . 'assets')) {
    mkdir(plugin_dir_path(__FILE__) . 'assets', 0777, true);
}
if (!file_exists(plugin_dir_path(__FILE__) . 'assets/fonts')) {
    mkdir(plugin_dir_path(__FILE__) . 'assets/fonts', 0777, true);
}
if (!file_exists(plugin_dir_path(__FILE__) . 'assets/stylesheets')) {
    mkdir(plugin_dir_path(__FILE__) . 'assets/stylesheets', 0777, true);
}


ob_start();

add_action('shutdown', function() {
    $final = '';

    // We'll need to get the number of ob levels we're in, so that we can iterate over each, collecting
    // that buffer's output into the final output.
    $levels = ob_get_level();

    for ($i = 0; $i < $levels; $i++) {
        $final .= ob_get_clean();
    }

    // Apply any filters to the final output
    echo apply_filters('final_output', $final);
}, 0);


add_filter('final_output', function($output) {


    $htmlDom = new DOMDocument;

    //Parse the HTML of the page using DOMDocument::loadHTML
    @$htmlDom->loadHTML($output);

    //Extract the links from the HTML.
    $links = $htmlDom->getElementsByTagName('link');

    //Array that will contain our extracted links.
    $extractedLinks = array();

    //Loop through the DOMNodeList.
    //We can do this because the DOMNodeList object is traversable.
    foreach($links as $link){

        //Get the link text.
        $linkText = $link->nodeValue;
        //Get the link in the href attribute.
        $linkHref = $link->getAttribute('href');

        //If the link is empty, skip it and don't
        //add it to our $extractedLinks array
        if(strlen(trim($linkHref)) == 0){
            continue;
        }

        //Skip if it is a hashtag / anchor link.
        if($linkHref[0] == '#'){
            continue;
        }


        // str_contains( $haystack:string, $needle:string )
        if (str_contains($linkHref, 'fonts.googleapis.com')) {
            //Add the link to our $extractedLinks array.
            // $extractedLinks[] = $linkText;

            array_push($extractedLinks, $linkHref);
        }

    }


    echo "<pre>";
    $stylesheets = array();
    foreach($extractedLinks as $link){
        // echo $link;
        // echo "<br>";


        $style = get_css_from_url($link);
        array_push($stylesheets, $style );

        $google_fonts_links = array();

        // split string by space
        $stylesheet = explode(" ", $style);
        foreach($stylesheet as $line){
            if(str_contains( $line, "https://fonts.gstatic.com/" )){
                $line = str_replace("url(", "", $line);
                $line = str_replace(")", "", $line);
                $line = str_replace("'", "", $line);
                $line = str_replace('"', "", $line);
                $line = str_replace(";", "", $line);
                array_push($google_fonts_links, $line);
            }

        }



        // foreach google fonts link
        foreach($google_fonts_links as $fontlink){
            $font = query_font_from_url($fontlink);
            
            // split fontlink by family=
            $fontlink_parts = explode("com/s/", $fontlink);
            
            $fontname = $fontlink_parts[1];
            // save font to local folder

            if($fontname == "" || strlen($fontname) == 0){
                continue;
            }


            if(strlen($fontname) > 30){
                $oldfontname = $fontname;
                $fontname = substr($fontname, 0, 30);
                str_replace($oldfontname, $fontname, $style);
            }



            save_to_folder("assets/fonts", $fontname, $font);

            // replace google fonts link with local link
            $style = str_replace($fontlink, plugin_dir_path( __FILE__ ) . "/assets/fonts/" . $fontname, $html);


        }

        $stylename = urlencode(explode("?family=",$link)[1]);

        // save stylesheet to local folder

        


        if(strlen($stylename) > 30){
            $oldstylename = $stylename;
            $stylename = substr($stylename, 0, 30);
            str_replace($oldstylename, $stylename, $output);
        }


        save_to_folder("assets/stylesheets", $stylename, $style);

        // replace googleapis link with local link
        $html = str_replace($link, plugin_dir_path( __FILE__ ) . "/assets/stylesheets/" . $stylename, $html);
    }
    echo "</pre>";


    
    $output = str_replace("https://fonts.googleapis.com/css?family=", '/wp-content/plugins/anti-google-fonts/assets/stylesheets/', $output);




    // write to file
    save_to_folder("assets", "test", $extractedLinks);

    return $output;
});

function get_css_from_url($url){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}


function query_font_from_url($url){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}

function save_to_folder($folder, $name, $content){

    // url encode name
    $name = urlencode($name);
    
    $plugindir = plugin_dir_path( __FILE__ );
    
    $filename = $plugindir . $folder . "/" . $name;
    file_put_contents($filename, $content);
}
