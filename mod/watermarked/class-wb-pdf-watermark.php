<?php


if ( ! class_exists( 'WB_PDF_Watermark' ) ) {

	class WB_PDF_Watermark {

		/**
		 * Single instance of the class
		 * @var object
		 */
		private static $_instance = null;



		/**
		 * Get single instance of class
		 * @return object WB_PDF_Stamper
		 */
		public static function instance ( ) {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self(  );
			}
			return self::$_instance;
		} // End instance()

		/**
		 * Get a temporary file name (e.g. for bringing a remote file locally for processing)
		 * @param  string $original_file
		 * @return string
		 */
		private static function get_temporary_file_name( $original_file, $USER ) {

			$file_name = basename( $original_file );

			
			$prefix = sha1($USER->email);
			
			$file_name = $prefix . '-' . $file_name . '.pdf'; 

			

			// Remove any query from the remote file name to avoid exposing query parameters and
			// a generally ugly file name to the user (e.g. for S3 served files)
			$file_name = preg_replace( '/[?].*$/', '', $file_name );

			// In the unlikely event that file_name is now empty, use a generic filename
			if ( empty( $file_name ) ) {
				$file_name = 'untitled.pdf';
			}

			return $file_name;

		}


		/**
		 * Get a temporary file folder (e.g. for bringing a remote file locally for processing)
		 * @return string
		 */
		private static function get_temporary_file_folder() {

					//require_once('../../config.php');
					global $CFG; 
					$new_file_path = $CFG->dataroot . '/watermarked/';
					
					
					if ( ! file_exists( $new_file_path ) ) {
						mkdir($new_file_path);
					}

				return $new_file_path;

		}


	
		/**
		 * Actual watermark of a PDF file
		 * @param  string $original_file Original file path
		 * @param  int $order_id
		 * @param  int $product_id
		 * @return string New file path
		 */
		public function watermark_file( $original_file, $USER) {
			if ( ! class_exists( 'WB_PDF_Watermarker' ) ) {
				require_once 'class-wb-pdf-watermarker.php';
			}

			$pdf = new WB_PDF_Watermarker();

			
			
			$text = 'Licencja dla {first_name} {last_name} {email}
					{license} {site_name} {now}. ';
			$new_file_path = self::get_temporary_file_folder();
			$file_name = self::get_temporary_file_name( $original_file, $USER );

			try	{
				$pdf->watermark( $original_file, $new_file_path . $file_name, $text, $USER );
			}
			catch(Exception $x)	{
				echo 'Error: ' . json_encode($x);
			}

			$new_file = $new_file_path . $file_name;



			return $new_file;
		}


	}

}




