<?php

if ( ! class_exists( 'QSOT_QRImage' ) ):
// qsot version of the qrimage class, so that we dont get the headers
class QSOT_QRImage {
	// create a base64 version of the jpeg output
	public static function jpg_base64( $frame, $pixelPerPoint = 8, $outerFrame = 4, $q = 85 ) {
		// buffer the ouput
		ob_start();

		// get the image
		list( $image, $w, $h ) = self::image( $frame, $pixelPerPoint, $outerFrame );
		
		// output the raw image data
		ImageJpeg($image, null, $q);
		
		// clean memory
		ImageDestroy($image);

		// grab the output and clean the buffer
		$out = ob_get_contents();
		ob_end_clean();

		// encode and return
		return array( 'data:image/jpg;base64,' . base64_encode( $out ), $w, $h );
	}

	// copied and reformatted function from qrimage::image (also elevated visibility)
	protected static function image( $frame, $pixelPerPoint = 4, $outerFrame = 4 ) {
		$h = count( $frame );
		$w = strlen( $frame[0] );
		
		$imgW = $w + 2 * $outerFrame;
		$imgH = $h + 2 * $outerFrame;
		
		$base_image = ImageCreate( $imgW, $imgH );
		
		$col[0] = ImageColorAllocate( $base_image, 255, 255, 255 );
		$col[1] = ImageColorAllocate( $base_image, 0, 0, 0 );

		imagefill( $base_image, 0, 0, $col[0] );

		for ( $y = 0; $y < $h; $y++ ) {
			for ( $x = 0; $x < $w; $x++ ) {
				if ( $frame[ $y ][ $x ] == '1' ) {
					ImageSetPixel( $base_image, $x + $outerFrame, $y + $outerFrame, $col[1] ); 
				}
			}
		}
		
		$target_image = ImageCreate( $imgW * $pixelPerPoint, $imgH * $pixelPerPoint );
		ImageCopyResized( $target_image, $base_image, 0, 0, 0, 0, $imgW * $pixelPerPoint, $imgH * $pixelPerPoint, $imgW, $imgH );
		ImageDestroy( $base_image );
		
		return array( $target_image, $imgW * $pixelPerPoint, $imgH * $pixelPerPoint );
	}
}
endif;
