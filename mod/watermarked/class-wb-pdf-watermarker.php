<?php
use setasign\FpdiProtection\FpdiProtection;


if ( ! class_exists( 'WB_PDF_Watermarker' ) ) {

	class WB_PDF_Watermarker {

		private $pdf;

		/**
		 * Constructor
		 * @return void
		 */
		public function __construct() {
			// Include the PDF libs
			$this->includes();

			$this->pdf = new FpdiProtection( 'P', 'pt' ,'A4');

		} // __construct()

		/**
		 * Include PDF libraries
		 * @return void
		 */
		public function includes() {
			// Include FPDF
			require('lib/fpdf/fpdf.php');
			require_once('lib/fpdi/autoload.php');
			require_once('lib/fpdi-protection/autoload.php');
			
		} // End includes()

		/**
		 * Test file for open-ability
		 * @return int number of pages in document, false otherwise
		 */
		public function tryOpen( $file ) {

			try {
				$pagecount = $this->pdf->setSourceFile( $file );
			} catch ( Exception $e ) {
				echo 'Exception' . $e;
				return false;
			}

			return $pagecount;
		}

		/**
		 * Apply the watermark to the file
		 * @return void
		 */
		public function watermark( $original_file, $new_file, $text, $USER, $preview = false ) {
			// Set up the PDF file
			$pagecount = $this->tryOpen( $original_file );
			if ( false === $pagecount ) {
				//echo  $_SERVER['DOCUMENT_ROOT'];
				die( 'File not found: '  . $new_file );
			}

			$this->pdf->SetAutoPageBreak( 0 );
			$this->pdf->SetMargins( 0, 0 );

			// Get WB PDF Watermark Settings
			
			$x_pos             = 'left';
			$y_pos             = 'top';
			$opacity           = 1;
			$override          = true;
			$horizontal_offset = 0;
			$vertical_offset   = 0;
			$display_pages     = 'all';

			$color       = $this->hex2rgb( '#b30859');
			$font        = 'times';
			$size        = 10;
			$line_height = is_numeric( $size ) ? $size : 8;
			$bold        = true;
			$italics     = false;
			$underline   = false;

				// Build style var
				$style = '';
				if ( $bold ) {
					$style .= 'B';
				}
				if ( $italics ) {
					$style .= 'I';
				}
				if ( $underline ) {
					$style .= 'U';
				}

				// Assign font
				$this->pdf->SetFont( $font, $style, $size );

				$text = $this->parse_template_tags(  $text, $USER );
				if ( function_exists( 'iconv' ) ) {
					$text = iconv( 'UTF-8', 'ISO-8859-1//TRANSLIT', $text );
				} else {
					$text = html_entity_decode( utf8_decode( $text ) );
				}
					
					
				// Get number of lines of text, can use a new line to go to a new line
				$lines = 1;
				if ( stripos( $text, "\n" ) !== FALSE ) {
					$lines = explode( "\n", $text );
					$text = explode( "\n", $text );
					$lines = count( $lines );
					$longest_text = 0;
					foreach ( $text as $line ) {
						if ( $this->pdf->GetStringWidth( $line ) > $this->pdf->GetStringWidth( $longest_text ) ) {
							$longest_text = $this->pdf->GetStringWidth( $line );
						}
					}
				} else {
					$longest_text = $this->pdf->GetStringWidth( $text );
				}

				// Loop through pages to add the watermark
				for ( $i = 1; $i <= $pagecount; $i++ ) {
					$tplidx = $this->pdf->importPage( $i );
					$specs = $this->pdf->getTemplateSize( $tplidx );
					$orientation = ( $specs['height'] > $specs['width'] ? 'P' : 'L' );

					$this->pdf->addPage( $orientation, array( $specs['width'], $specs['height'] ) );
					$this->pdf->useTemplate( $tplidx );

					// Check if we must skip this page based on the display on page setting
					if ( 'first' == $display_pages && 1 !== $i ) {
						continue;
					} else if ( 'last' == $display_pages && $i !== $pagecount ) {
						continue;
					} else if ( 'alternate' == $display_pages ) {
						if ( ( $i % 2 ) == 0 ) {
							continue;
						}
					}

					// Horizontal Alignment for Cell function
					$x = 0;
					if ( 'right' == $x_pos ) {
						$x = $specs['width'];
					} elseif( 'center' == $x_pos ) {
						$x = ( $specs['width'] / 2 );
					} elseif( 'left' == $x_pos ) {
						$x = 0;
					}

					// Vertical Alignment for setY function
					$y = 0;
					if ( 'bottom' == $y_pos ) {
						$y = $specs['heigth'] - ( ( $line_height * $lines ) + 7 );
					} elseif( 'middle' == $y_pos ) {
						$y = ( $specs['heigth'] / 2 ) - ( $line_height / 2 );
					} elseif( 'top' == $y_pos ) {
						$y = $line_height / 2;
					}

					// Vertical offset
					$y += $vertical_offset;

					$this->pdf->setY( $y );
					//$this->pdf->setAlpha( $opacity );
					$this->pdf->SetTextColor( $color[0], $color[1], $color[2] );

					// Put the text watermark down with Cell
					if ( is_array( $text ) ) {
						foreach ( $text as $line ) {
							if ( 'right' == $x_pos ) {
								$_x = $x - ( $this->pdf->GetStringWidth( $line ) + 7 );
							} elseif( 'center' == $x_pos ) {
								$_x = $x - ( $this->pdf->GetStringWidth( $line ) / 2 );
							} else {
								$_x = $x;
							}

							// Horizontal Offset
							$_x += $horizontal_offset;

							$this->pdf->SetXY( $_x, $y );
							$this->pdf->Write( $line_height, $line );
							$y += $line_height;
							//$this->pdf->Cell( 0, 0, $line, 0, 0, $x );
							//$this->pdf->Ln( $line_height );
						}
					} else {
						if ( 'right' == $x_pos ) {
							$_x = $x - ( $this->pdf->GetStringWidth( $text ) + 7 );
						} elseif( 'center' == $x_pos ) {
							$_x = $x - ( $this->pdf->GetStringWidth( $text ) / 2 );
						} else {
							$_x = $x;
						}

						// Horizontal Offset
						$_x += $horizontal_offset;

						$this->pdf->SetXY( $_x, $y );
						$this->pdf->Write( $line_height, $text );
						//$this->pdf->Cell( 0, 0, $text, 0, 0, $x );
					}
					//$this->pdf->setAlpha( 1 );

				} // End forloop
/*
			} elseif ( $type && 'image' == $type ) {
				$image      = get_option( 'woocommerce_pdf_watermark_image' );
				if ( 'yes' == $override ) {
					$image = get_post_meta( $product_id, '_pdf_watermark_image', true );
				}
				$image      = str_replace( WP_CONTENT_URL, WP_CONTENT_DIR, $image );
				$image_info = getimagesize( $image );
				$width      = $image_info[0];
				$height     = $image_info[1];

				for ( $i = 1; $i <= $pagecount; $i++ ) {
					$tplidx = $this->pdf->importPage( $i );
					$specs = $this->pdf->getTemplateSize( $tplidx );
					$orientation = ( $specs['h'] > $specs['w'] ? 'P' : 'L' );

					$this->pdf->addPage( $orientation, array( $specs['w'], $specs['h'] ) );
					$this->pdf->useTemplate( $tplidx );

					// Check if we must skip this page based on the display on page setting
					if ( 'first' == $display_pages && 1 !== $i ) {
						continue;
					} else if ( 'last' == $display_pages && $i !== $pagecount ) {
						continue;
					} else if ( 'alternate' == $display_pages ) {
						if ( ( $i % 2 ) == 0 ) {
							continue;
						}
					}

					// Horizontal alignment
					$x = 0;
					if ( 'right' == $x_pos ) {
						$x = $specs['w'] - ( $width * 20 / 72 );
					} elseif ( 'center' == $x_pos ) {
						$x = ( $specs['w'] / 2 ) - ( $width * 20 / 72 );
					} elseif ( 'left' == $x_pos ) {
						$x = '0';
					}
					// Horizontal Offset
					$x += $horizontal_offset;

					// Vertical alignment
					$y = 0;
					if ( 'bottom' == $y_pos ) {
						$y = $specs['h'] - ( $height );
					} elseif ( 'middle' == $y_pos ) {
						$y = ( $specs['h'] / 2 ) - ( $height );
					} elseif ( 'top' == $y_pos ) {
						$y = '0';
					}
					// Vertical offset
					$y += $vertical_offset;

					$this->pdf->SetAlpha( $opacity );
					$this->pdf->Image( $image, $x, $y );
					$this->pdf->SetAlpha( 1 );
				} // End forloop
			} // End else for image type
*/
			// Apply protection settings
			$password_protect      =  'no';
			$do_not_allow_copy     = 'no' ;
			$do_not_allow_print    = 'no';
			$do_not_allow_modify   = 'no';
			$do_not_allow_annotate = 'no';
			$protection_array = array();

			if ( 'no' == $do_not_allow_copy ) {
				$protection_array[] = 'copy';
			}
			if ( 'no' == $do_not_allow_print ) {
				$protection_array[] = 'print';
			}
			if ( 'no' == $do_not_allow_modify ) {
				$protection_array[] = 'modify';
			}
			if ( 'no' == $do_not_allow_annotate ) {
				$protection_array[] = 'annot-forms';
			}
			$user_pass = '';


			$this->pdf->SetProtection( $protection_array, '' );

			if ( $preview ) {
				$this->pdf->Output();
			} else {
				$this->pdf->Output( $new_file, 'F' );
			}
		}

		/**
		 * Convert HEX color code to RGB
		 * @param  string $color HEX color code
		 * @return array
		 */
		public function hex2rgb( $color ) {
			if ( $color[0] == '#')
				$color = substr( $color, 1 );

			if ( strlen( $color ) == 6 ) {
				list( $r, $g, $b ) = array(
					$color[0] . $color[1],
					$color[2] . $color[3],
					$color[4] . $color[5]
				);
			} elseif ( strlen( $color ) == 3 ) {
				list( $r, $g, $b ) = array(
					$color[0] . $color[0],
					$color[1] . $color[1],
					$color[2] . $color[2]
				);
			} else {
				return false;
			}

			$r = hexdec( $r );
			$g = hexdec( $g );
			$b = hexdec( $b );

			return array( $r, $g, $b );
		} // End hex2rgb()

		/**
		 * Parse text for template tags and populate it
		 * @param  int $order_id
		 * @param  string $text
		 * @return string
		 */
		public function parse_template_tags($text, $USER ) {
			if ( false === strpos( $text, '{' ) ) {
				return $text;
			}

			$first_name = $USER->firstname;
			$last_name = $USER->lastname;
			$email = $USER->email;
			$zogonek = "\u{017c}";
			$license = 'All rights reserved.';
			$now = new DateTime();
			$now = $now->format( 'Y-m-d H:i:s' );
			$site_name = 'Instytut Psychodietetyki';
			$site_url = 'instytutpsychodietetyki.pl';

			$unparsed_text = $parsed_text = $text;

			$parsed_text = str_replace( '{first_name}', $first_name, $parsed_text );
			$parsed_text = str_replace( '{last_name}', $last_name, $parsed_text );
			$parsed_text = str_replace( '{email}', $email, $parsed_text );
			$parsed_text = str_replace( '{now}', $now, $parsed_text );
			$parsed_text = str_replace( '{license}', $license, $parsed_text );
			$parsed_text = str_replace( '{site_name}', $site_name, $parsed_text );
			$parsed_text = str_replace( '{site_url}', $site_url, $parsed_text );
			
			return $parsed_text;
		} // End parse_template_tags()
	}
}
