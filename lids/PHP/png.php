<?php declare (strict_types = 1);

namespace lids\PHP;

/**
 * PNG Class
 *
 * Moves, resizes, recolors, crops, and
 * Shrinks files to just above "ambiguous" state
 *
 * @author David Pulse <inland14@live.com>
 * @api v3.1.5
 */
class PNG
{

    public $epochs = 2;

    /**
     *   public function correct_for_cat
     *   @param Branches &$input Make ->cat syntactically corect
     *
     *   @return bool
     */
    public function correct_for_cat(Branches &$input) {
        
        $output = new Branches();
        $temp = [];
        if (is_string($input->cat) && $input->cat != "") {
            $temp[] = $input->cat;
        } else if (is_string($input->cat) && $input->cat == "") {
            $temp[] = "misc";
        } else {
            $temp[] = "misc";
            //echo "Error: Unknown syntax in \$node->cat. It is neither a array nor a string. Choose one!";
            //exit();
        }
        foreach ($input as $k => $v) {
            if ($k == "cat")
                $output->$k = $temp;
            else
                $output->$k = $v;
        }
        $input = $output;
    }

    /**
     *   public function search_imgs_sub_dir
     *   @param Branches &$input   Search through files in sub-dir for prefabricated data
     *
     *   Searches for and finds thumbnail_img
     *   name.
     *
     *   @return bool
     */
    public function search_imgs_sub_dir(Tier $tier, Branches &$input, string $dir, string $bri, string $sub_folder, bool $opt = false)
    {
        $this->correct_for_cat($input);

        foreach (scandir($dir . $sub_folder) as $sub_file) {
            if ($sub_file == '.' || $sub_file == "..") {
                continue;
            }
            if (is_dir($dir . "/" . $sub_folder . "/" . $sub_file)) {
                $tier->search_imgs_sub_dir($tier, $input, $dir, $bri, $sub_folder . "/" . $sub_file, $opt);
                continue;
            }
            if (!\file_exists($dir . "/" . $sub_folder . "/" . $sub_file))
                continue;
            $input->cat[] = str_replace("/","", $sub_folder);
            $input->image_sha1 = $dir . "/" . $sub_folder . "/" . $sub_file;

            if ($opt == true) {
                $this->img_contrast($tier, $input, $sub_file, $bri);
            }
        }
        return $input;
    }

    public function img_contrast(Tier $tier, Branches $input, string $file, string $bri = "")
    {

        $svf = \file_get_contents($input->image_sha1);
        if ($bri == "")
            $bri = \file_get_contents($file);
        $i = 0;
        $intersect = 0;
        while ($i < strlen($bri) && $i < strlen($svf)) {
            if ($bri[$i] == $svf[$i]) {
                $intersect++;
            }
            $i++;
            //echo ($intersect / $i) . " ";
            if ($i > (min(strlen($bri),strlen($svf))/min(strlen($bri),strlen($svf)))*25 && $intersect / $i > 0.04)
             {
                $input->crops = array($file, $intersect / $i);
                $tier->label_search($input);
                $RETURN = 0;
                flush();
                \ob_flush();
                return 1;
            }
        }
        return 0;
    }

    /**
     *   public function find_tier
     *   @param Branches $src Gives file source and other information
     *
     *   Shrinks files to just above "ambiguous" state
     *
     *   @return Array
     */
    public function find_tier(Branches $src): Branches
    {
        $temp = "";
        if (is_array($src->cat) && count($src->cat) > 0) {
            $temp = $src->cat[0];
        } else if (!is_array($src->cat) && $src->cat != "") {
            $temp = $src->cat;
        } else if (is_string($src->cat) && $src->cat == "") {
            $temp = "misc";
        } else {
            echo "Error: Unknown syntax in \$node->cat. It is neither a array nor a string. Choose one!";
            exit();
        }
        $src->cat[0] = $temp;
        $src->sha_name = hash_file('SHA1', $src->origin, false);
        if (!is_dir((__DIR__) . "/../dataset/" . $src->cat[0] . "/") && $src->cat != "dataset") {
            \mkdir((__DIR__) . "/../dataset/" . $src->cat[0] . "/");
        }

        $src->image_sha1 = (__DIR__) . "/../dataset/" . $src->cat[0] . "/" . $src->sha_name;
        $src->crops = array($src->sha_name, 0);

        if (\file_exists($src->image_sha1)) {
            return $src;
        }
        $scale = imagecreatefromstring(file_get_contents($src->origin));

        \imagepng($scale, (__DIR__) . "/../dataset/" . $src->cat[0] . "/" . $src->sha_name);

        #$img = imagecreatefrompng($src->image_sha1);
        $this->epoch($src, (__DIR__) . "/../dataset/" . $src->cat[0] . "/" . $src->sha_name);

        return $src;
    }

    /**
     *   public function epoch
     *   @param string $Handle Filename and path
     *
     *   returns brightness of photos
     *
     */
    public function epoch(&$src, $dest)
    {
        $s = 1;
        $file = $src->origin;
        while ($s < $this->epochs)
        {  

            $scale = imagecreatefromstring(file_get_contents($file));
            \imagepng($scale, (__DIR__) . "/../dataset/" . $src->cat[0] . "/" . $src->sha_name);
            $scale = imagescale($scale,100*$s,100*$s,IMG_NEAREST_NEIGHBOUR);
            $file = $this->get_weighted_state($scale, (__DIR__) . "/../dataset/" . $src->cat[0] . "/" . $src->sha_name);
            $s++;
        }
    }

    /**
     *   public function get_weighted_state
     *   @param string $Handle Filename and path
     *
     *   returns brightness of photos
     *
     */
    public function get_weighted_state(&$Handle, $dest)
    {
        $width = imagesx($Handle);
        $height = imagesy($Handle);

        //After return, send to function comparing
        //weight: how much of formula relies on this
        // X1 \__( ) datapoints
        // X2 /$image = imagecreatetruecolor(400, 300);
        $sku_height = $height;
        $sku_width = $width;
        $img = \imagecreatetruecolor($sku_width, $sku_height); // bias can be Height of radiogram/sku (ex. 64,127,256)
        $bg = imagecolorallocate($img, 255, 255, 255);

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgb_l1 = imagecolorat($Handle, $x, $y);
                $red = (int) ($rgb_l1 >> 16) & 0xFF; 
                $green = (int) ($rgb_l1 >> 8) % 256 & 0xFF;
                $blue = (int) ($rgb_l1 % 256) & 0xFF;

                $red = $red % 16;
                $green = $green % 16;
                $blue = $blue % 16;

                $red = str_pad(decbin($red), 4, "0"); 
                $green = str_pad(decbin($green), 4, "0"); 
                $blue = str_pad(decbin($blue), 4, "0");

                //imagesetpixel($img, (int) ($x*4-1), $y*3, $rgb_l1);
                // rows
                if (intval($red[3]) - intval($red[2]) - intval($red[1]) - intval($red[0]) == 0)
                    imagesetpixel($img, (int) ($x*4+1), $y*4, 0x00);
                else if (intval($red[3]) - intval($red[2]) - intval($red[1]) - intval($red[0]) == -1)
                    imagesetpixel($img, (int) ($x*4+1), $y*4, 0xdddddd);
                else if (intval($red[3]) - intval($red[2]) - intval($red[1]) - intval($red[0]) == -2)
                    imagesetpixel($img, (int) ($x*4+1), $y*4, 0xaaaaaa);
                else
                    imagesetpixel($img, (int) ($x*4+1), $y*4, 0xFFFFFF);
                // row 2
                if (intval($green[3]) - intval($green[2]) - intval($green[1]) - intval($green[0]) == 0)
                    imagesetpixel($img, (int) ($x*4+2), $y*4, 0x00);
                else if (intval($green[3]) - intval($green[2]) - intval($green[1]) - intval($green[0]) == -1)
                    imagesetpixel($img, (int) ($x*4+2), $y*4, 0xdddddd);
                else if (intval($green[3]) - intval($green[2]) - intval($green[1]) - intval($green[0]) == -2)
                    imagesetpixel($img, (int) ($x*4+2), $y*4, 0xaaaaaa);
                else
                    imagesetpixel($img, (int) ($x*4+2), $y*4, 0xFFFFFF);
                // row 3
                if (intval($blue[3]) - intval($blue[2]) - intval($blue[1]) - intval($blue[0]) == 0)
                    imagesetpixel($img, (int) ($x*4), $y*4, 0x00);
                else if (intval($blue[3]) - intval($blue[2]) - intval($blue[1]) - intval($blue[0]) == -1)
                    imagesetpixel($img, (int) ($x*4), $y*4, 0xdddddd);
                else if (intval($blue[3]) - intval($blue[2]) - intval($blue[1]) - intval($blue[0]) == -2)
                    imagesetpixel($img, (int) ($x*4), $y*4, 0xaaaaaa);
                else
                    imagesetpixel($img, (int) ($x*4), $y*4, 0xFFFFFF);

                // columns
                if (intval($blue[0]) - intval($green[0]) - intval($red[0]) == 0)
                    imagesetpixel($img, (int) ($x*4+1), $y*3+2, 0x00);
                else if (intval($blue[0]) - intval($green[0]) - intval($red[0]) == -1)
                    imagesetpixel($img, (int) ($x*4+1), $y*3+2, 0xdddddd);
                else if (intval($blue[0]) - intval($green[0]) - intval($red[0]) == -2)
                    imagesetpixel($img, (int) ($x*4+1), $y*3+2, 0xaaaaaa);    
                else
                    imagesetpixel($img, (int) ($x*4+1), $y*3+2, 0xFFFFFF);
                // col 2
                if (intval($blue[1]) - intval($green[1]) - intval($red[1]) == 0)
                    imagesetpixel($img, (int) ($x*4+2), $y*3+1, 0x00);
                else if (intval($blue[1]) - intval($green[1]) - intval($red[1]) == -1)
                    imagesetpixel($img, (int) ($x*4+1), $y*3+1, 0xdddddd);
                else if (intval($blue[1]) - intval($green[1]) - intval($red[1]) == -2)
                    imagesetpixel($img, (int) ($x*4+1), $y*3+1, 0xaaaaaa);
                else
                    imagesetpixel($img, (int) ($x*4+2), $y*3+1, 0xFFFFFF);
                // col 3
                if (intval($blue[2]) - intval($green[2]) - intval($red[2]) == 0)
                    imagesetpixel($img, (int) ($x*4), $y*4, 0x00);
                else if (intval($blue[2]) - intval($green[2]) - intval($red[2]) == -1)
                    imagesetpixel($img, (int) ($x*4+1), $y*4, 0xdddddd);
                else if (intval($blue[2]) - intval($green[2]) - intval($red[2]) == -2)
                    imagesetpixel($img, (int) ($x*4+1), $y*4, 0xaaaaaa);
                else
                    imagesetpixel($img, (int) ($x*4), $y*4, 0xFFFFFF);
            }
        }
        \imagefilter($img, IMG_FILTER_GRAYSCALE);
        \imagepng($img, $dest);
        return $dest;
    }
}
