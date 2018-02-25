<?php
	function getData(){
		$src = "../src/cards_data.csv";
		$results = array();

		if (($handle = fopen( "src/" . $src, "r" )) !== FALSE) 
		{
			$names = fgetcsv($handle, 1000, ",");

			while (( $data = fgetcsv($handle, 1000, ",")) !== FALSE) 
			{
				$obj = array();
				for ($i = 0; $i < count($names) - 1; $i ++)
				{
					if ($data[$i + 1] != "")
					{
						$obj[$names[$i + 1]] = $data[$i + 1];
					}
				}

				$results[$data[0]] = $obj;
			}

			fclose($handle);
		}

		return $results;
	}

	function hex2rgb($hex) {
		$hex = str_replace("#", "", $hex);

		if(strlen($hex) == 3) {
			$r = hexdec(substr($hex,0,1).substr($hex,0,1));
			$g = hexdec(substr($hex,1,1).substr($hex,1,1));
			$b = hexdec(substr($hex,2,1).substr($hex,2,1));
		} else {
			$r = hexdec(substr($hex,0,2));
			$g = hexdec(substr($hex,2,2));
			$b = hexdec(substr($hex,4,2));
		}
		$rgb = array($r, $g, $b);
	
		return $rgb;
	}

	function random_color_part() {
		return str_pad( dechex( mt_rand( 0, 255 ) ), 2, '0', STR_PAD_LEFT);
	}

	function random_color() {
		return random_color_part() . random_color_part() . random_color_part();
	}

	function getStyles(){
		ob_start();
		include("src/style.php");
		$content = ob_get_clean();

		return json_decode($content, true);
	}

	function formattedMessage($canvas_w, $canvas_h, $font_file, $font_size, $angle, $message)
	{
		$words = explode(" ", $message);
		$dup_message = "";

		foreach ($words as $w)
		{
			if ($dup_message == "") 
			{
				$tmp1 = $w;
				$tmp2 = $w;
			}
			else
			{
				$tmp1 = $dup_message . " " . $w;
				$tmp2 = $dup_message . "\n" . $w;
			}
			
			$type_space = calculateTextBox($font_size, $angle, $font_file, $tmp1);

			if ( $type_space["width"] <= $canvas_w && $type_space["height"] <= $canvas_h)
			{
				$dup_message = $tmp1;
				continue;
			}

			$type_space = calculateTextBox($font_size, $angle, $font_file, $tmp2);
			if ( $type_space["width"] <= $canvas_w && $type_space["height"] <= $canvas_h)
			{
				$dup_message = $tmp2;
				continue;
			}
		}

		return $dup_message;
	}

	function calculateTextBox($fontSize, $fontAngle, $fontFile, $text) { 
	    $rect = imagettfbbox($fontSize, $fontAngle, $fontFile, $text); 

	    $minX = min(array($rect[0], $rect[2], $rect[4], $rect[6])); 
	    $maxX = max(array($rect[0], $rect[2], $rect[4], $rect[6])); 
	    $minY = min(array($rect[1], $rect[3], $rect[5], $rect[7])); 
	    $maxY = max(array($rect[1], $rect[3], $rect[5], $rect[7])); 
	    
	    return array( 
	     "left"   => abs($minX) - 1, 
	     "top"    => abs($minY) - 1, 
	     "width"  => $maxX - $minX + 6, 
	     "height" => $maxY - $minY + 6, 
	     "box"    => $rect 
	    );
	}

	function splitArea($area, $iter, $min_space)
	{
		for ($i = 0; $i < $iter - 1; $i ++)
		{
			do {
				$sp_index = rand() % count($area);
				$sp = $area[$sp_index];

				$slice = rand(1, 3);
				$direction = rand() % 2;

				if ($direction == 0)
				{
					// horizontally
					$sp1 = [$sp[0], $sp[1], ($sp[2] - $sp[0]) * $slice / 5 + $sp[0], $sp[3]];
					$sp2 = [($sp[2] - $sp[0]) * $slice / 5 + $sp[0], $sp[1], $sp[2], $sp[3]];
				}
				else
				{
					// vertically
					$sp1 = [$sp[0], $sp[1], $sp[2], ($sp[3] - $sp[1]) * $slice / 5 + $sp[1]];
					$sp2 = [$sp[0], ($sp[3] - $sp[1]) * $slice / 5 + $sp[1], $sp[2], $sp[3]];
				}

				$sp1_area = ($sp1[2] - $sp1[0]) * ($sp1[3] - $sp1[1]);
				$sp2_area = ($sp2[2] - $sp2[0]) * ($sp2[3] - $sp2[1]);
			}while ($sp1_area < $min_space && $sp2_area < $min_space);

			// merging
			$new_area = [];

			for ($j = 0; $j < $sp_index; $j ++)
			{
				$new_area[count($new_area)] = $area[$j];
			}

			for ($j = $sp_index + 1; $j < count($area); $j ++)
			{
				$new_area[count($new_area)] = $area[$j];
			}

			$new_area[count($new_area)] = $sp1;
			$new_area[count($new_area)] = $sp2;

			$area = $new_area;
		}

		return $area;
	}

	function generateCard($name, $card, $frame = "default.png", $left = 50, $top = 50, $canvas_width = 0, $canvas_height = 0)
	{
		// create image
		$img = imageCreateFromPng("frames/" . $frame);
		imagealphablending($img, true);
		imagesavealpha($img, true);
		
		$img_width = imagesx($img);
		$img_height = imagesy($img);

		//get style information
		$styles = getStyles();

		// get canvas width / height
		if ($canvas_width == 0)
		{
			$canvas_width = $img_width - $left * 2;
		}

		if ($canvas_height == 0)
		{
			$canvas_height = $img_height - $top * 2;
		}

		// splice rectangle
		$area = [[$left, $top, $left + $canvas_width, $top + $canvas_height]];
		$area = splitArea($area, count($card), $canvas_width * $canvas_height / count($card) / 2);

		// default font styling
		$font_color = "#" . random_color();
		$font_file = "fonts/calligra.ttf";
		$font_size = 10;
		$angle = 30;
		$i = 0;

		foreach ($card as $writer => $message)
		{
/*			$font_color = $styles[$writer]['font_color'];
			$text_colour = imagecolorallocate( $img, hex2rgb($font_color)[0], hex2rgb($font_color)[1], hex2rgb($font_color)[2] );
			
			imagefilledrectangle($img, $area[$i][0], $area[$i][1], $area[$i][2], $area[$i][3], $text_colour);
			imagecolordeallocate( $img, $text_colour );
*/
			$font_file = "fonts/" . $styles[$writer]['font_name'];
			$font_size = 11;

			// box width
			$v_width = $area[$i][2] - $area[$i][0];
			$v_height = $area[$i][3] - $area[$i][1];

			$direction = rand() % 2;
			$angle = atan2($area[$i][3] - $area[$i][1], $area[$i][2] - $area[$i][0]) / 3.14 * 180;

			/*if ($angle >= 45) $angle = rand(70, 90);
			if ($angle < 45) $angle = rand(0, 20);*/
			if ($direction == 1) $angle = -$angle;

			do {
				$font_size --;		
				$tmp = formattedMessage($v_width, $v_height, $font_file, $font_size, $angle, $message);
			} while ($font_size > 5 && $tmp == "");

/*			do {
				$font_size --;

				if ($direction == 0)
				{
					$angle     = mt_rand(0, 90);
					$tmp_angle = $angle;

					do {
						$tmp = formattedMessage($v_width, $font_file, $font_size, $tmp_angle, $message);
						$tmp_angle --;

						$type_space = calculateTextBox($font_size, $angle, $font_file, $tmp);
						$v_height = $type_space["height"];

						if ($tmp == "") $v_height = ($area[$i][3] - $area[$i][1]) + 1;
					}while ($tmp == "" && $tmp_angle >= 0 && $v_height >= ($area[$i][3] - $area[$i][1]));

					if ($tmp_angle < 0)
					{
						$tmp_angle = $angle;
						do {
							$tmp = formattedMessage($v_width, $font_file, $font_size, $tmp_angle, $message);
							$tmp_angle ++;

							$type_space = calculateTextBox($font_size, $angle, $font_file, $tmp);
							$v_height = $type_space["height"];

							if ($tmp == "") $v_height = ($area[$i][3] - $area[$i][1]) + 1;
						}while ($tmp == "" && $tmp_angle <= 90 && $v_height >= ($area[$i][3] - $area[$i][1]));
					}
				}
				else
				{
					$angle     = mt_rand(-90, 0);
					$tmp_angle = $angle;

					do {
						$tmp = formattedMessage($v_width, $font_file, $font_size, $tmp_angle, $message);
						$tmp_angle ++;

						$type_space = calculateTextBox($font_size, $angle, $font_file, $tmp);
						$v_height = $type_space["height"];

						if ($tmp == "") $v_height = ($area[$i][3] - $area[$i][1]) + 1;
					}while ($tmp == "" && $tmp_angle <= 0 && $v_height >= ($area[$i][3] - $area[$i][1]));

					if ($tmp_angle > 0)
					{
						$tmp_angle = $angle;
						do {
							$tmp = formattedMessage($v_width, $font_file, $font_size, $tmp_angle, $message);
							$tmp_angle --;

							$type_space = calculateTextBox($font_size, $angle, $font_file, $tmp);
							$v_height = $type_space["height"];

							if ($tmp == "") $v_height = ($area[$i][3] - $area[$i][1]) + 1;
						}while ($tmp == "" && $tmp_angle >= -90 && $v_height >= ($area[$i][3] - $area[$i][1]));
					}
				}

				$limit ++;
			} while ($v_height >= ($area[$i][3] - $area[$i][1]) && $limit < 5);
*/
			$message = $tmp;

			// draw
			$font_color = $styles[$writer]['font_color'];
			$text_colour = imagecolorallocate( $img, hex2rgb($font_color)[0], hex2rgb($font_color)[1], hex2rgb($font_color)[2] );

			$type_space = calculateTextBox($font_size, $angle, $font_file, $message);
			if ($angle > 0)
			{
				$area[$i][0] = $area[$i][0] - $type_space['box'][6];
				$area[$i][1] = $area[$i][1] - $type_space['box'][5];
			}
			else
			{
				$area[$i][0] = $area[$i][0] - $type_space['box'][0];
				$area[$i][1] = $area[$i][1] - $type_space['box'][7];
			}

			ImageTTFText($img, $font_size, $angle, $area[$i][0], $area[$i][1], $text_colour, $font_file, $message);
			imagecolordeallocate( $img, $text_colour );

			$i ++;
		}

		header( "Content-type: image/png" );
		imagepng( $img, "dist/" . $name . ".png" );
		imagedestroy( $img );

		header( "Content-type: text/html" );
	}
?>