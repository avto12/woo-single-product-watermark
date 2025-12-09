<?php
/**
 * Plugin Name: Woo Single Product Watermark (File-based)
 * Description: Adds a REAL watermark (modified image file) for WooCommerce single product images (main + gallery) ONLY on single product pages. Original media files remain clean.
 * Author: Avto + ChatGPT
 * Version: 1.1.0
 * Text Domain: woo-single-product-watermark
 */

if ( ! defined( 'ABSPATH' ) ) {
   exit;
}

class WSPW_Single_Product_Watermark {

   const OPT_ATTACHMENT_ID    = 'wspw_watermark_attachment_id';
   const OPT_POSITION         = 'wspw_watermark_position';
   const OPT_SIZE_MODE        = 'wspw_watermark_size_mode';
   const OPT_CUSTOM_WIDTH_PX  = 'wspw_watermark_custom_width_px';
   const OPT_MAX_WIDTH_PCT    = 'wspw_watermark_max_width_pct';

   public function __construct() {
      // Admin settings page
      add_action( 'admin_menu', array( $this, 'add_settings_page' ) );

      // Replace image URLs on single product with watermarked copies
      add_filter( 'wp_get_attachment_image_src', array( $this, 'filter_image_src' ), 10, 4 );
   }

   /**
    * Add submenu under WooCommerce
    */
   public function add_settings_page() {
      add_submenu_page(
         'woocommerce',
         __( 'Single Product Watermark', 'woo-single-product-watermark' ),
         __( 'Single Watermark', 'woo-single-product-watermark' ),
         'manage_woocommerce',
         'wspw-single-product-watermark',
         array( $this, 'render_settings_page' )
      );
   }

   /**
    * Get plugin options with defaults
    */
   protected function get_options() {
      $attachment_id = (int) get_option( self::OPT_ATTACHMENT_ID, 0 );
      $position      = get_option( self::OPT_POSITION, 'center' );
      $size_mode     = get_option( self::OPT_SIZE_MODE, 'default' );
      $custom_width  = (int) get_option( self::OPT_CUSTOM_WIDTH_PX, 150 );
      $max_pct       = (int) get_option( self::OPT_MAX_WIDTH_PCT, 40 );

      if ( $custom_width <= 0 ) {
         $custom_width = 150;
      }
      if ( $max_pct <= 0 || $max_pct > 100 ) {
         $max_pct = 40;
      }

      $allowed_positions = array( 'center', 'top_right', 'bottom_right', 'top_left', 'bottom_left' );
      if ( ! in_array( $position, $allowed_positions, true ) ) {
         $position = 'center';
      }

      if ( $size_mode !== 'default' && $size_mode !== 'custom' ) {
         $size_mode = 'default';
      }

      return array(
         'attachment_id' => $attachment_id,
         'position'      => $position,
         'size_mode'     => $size_mode,
         'custom_width'  => $custom_width,
         'max_pct'       => $max_pct,
      );
   }

   /**
    * Handle settings save
    */
   protected function handle_settings_save() {
      if ( ! isset( $_POST['wspw_settings_nonce'] ) ) {
         return;
      }
      if ( ! wp_verify_nonce( $_POST['wspw_settings_nonce'], 'wspw_save_settings' ) ) {
         return;
      }
      if ( ! current_user_can( 'manage_woocommerce' ) ) {
         return;
      }

      $attachment_id = isset( $_POST['wspw_watermark_attachment_id'] ) ? (int) $_POST['wspw_watermark_attachment_id'] : 0;
      $position      = isset( $_POST['wspw_watermark_position'] ) ? sanitize_text_field( $_POST['wspw_watermark_position'] ) : 'center';
      $size_mode     = isset( $_POST['wspw_watermark_size_mode'] ) ? sanitize_text_field( $_POST['wspw_watermark_size_mode'] ) : 'default';
      $custom_width  = isset( $_POST['wspw_watermark_custom_width_px'] ) ? (int) $_POST['wspw_watermark_custom_width_px'] : 150;
      $max_pct       = isset( $_POST['wspw_watermark_max_width_pct'] ) ? (int) $_POST['wspw_watermark_max_width_pct'] : 40;

      $allowed_positions = array( 'center', 'top_right', 'bottom_right', 'top_left', 'bottom_left' );
      if ( ! in_array( $position, $allowed_positions, true ) ) {
         $position = 'center';
      }

      if ( $size_mode !== 'default' && $size_mode !== 'custom' ) {
         $size_mode = 'default';
      }

      if ( $custom_width <= 0 ) {
         $custom_width = 150;
      }

      if ( $max_pct <= 0 || $max_pct > 100 ) {
         $max_pct = 40;
      }

      update_option( self::OPT_ATTACHMENT_ID,   $attachment_id );
      update_option( self::OPT_POSITION,        $position );
      update_option( self::OPT_SIZE_MODE,       $size_mode );
      update_option( self::OPT_CUSTOM_WIDTH_PX, $custom_width );
      update_option( self::OPT_MAX_WIDTH_PCT,   $max_pct );

      add_settings_error(
         'wspw_messages',
         'wspw_message',
         __( 'Settings saved.', 'woo-single-product-watermark' ),
         'updated'
      );
   }

   /**
    * Render admin settings page
    */
   public function render_settings_page() {
      if ( ! current_user_can( 'manage_woocommerce' ) ) {
         return;
      }

      // Handle save
      if ( isset( $_POST['wspw_save_settings'] ) ) {
         $this->handle_settings_save();
      }

      $opts = $this->get_options();
      $attachment_id = $opts['attachment_id'];
      $position      = $opts['position'];
      $size_mode     = $opts['size_mode'];
      $custom_width  = $opts['custom_width'];
      $max_pct       = $opts['max_pct'];

      $image_url = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'medium' ) : '';

      settings_errors( 'wspw_messages' );

      // Enqueue media for uploader
      wp_enqueue_media();
      ?>
       <div class="wrap">
           <h1><?php esc_html_e( 'Single Product Watermark', 'woo-single-product-watermark' ); ?></h1>

           <form method="post">
              <?php wp_nonce_field( 'wspw_save_settings', 'wspw_settings_nonce' ); ?>

               <table class="form-table" role="presentation">
                   <tbody>
                   <tr>
                       <th scope="row">
                           <label for="wspw_watermark_attachment_id">
                              <?php esc_html_e( 'Watermark image', 'woo-single-product-watermark' ); ?>
                           </label>
                       </th>
                       <td>
                           <div id="wspw-watermark-preview" style="margin-bottom:10px;">
                              <?php if ( $image_url ) : ?>
                                  <img src="<?php echo esc_url( $image_url ); ?>" alt="" style="max-width:200px;height:auto;border:1px solid #ccc;padding:3px;background:#fff;">
                              <?php else : ?>
                                  <em><?php esc_html_e( 'No image selected.', 'woo-single-product-watermark' ); ?></em>
                              <?php endif; ?>
                           </div>

                           <input type="hidden" id="wspw_watermark_attachment_id" name="wspw_watermark_attachment_id" value="<?php echo esc_attr( $attachment_id ); ?>">
                           <button type="button" class="button" id="wspw-select-image">
                              <?php esc_html_e( 'Select image', 'woo-single-product-watermark' ); ?>
                           </button>
                           <button type="button" class="button" id="wspw-remove-image">
                              <?php esc_html_e( 'Remove image', 'woo-single-product-watermark' ); ?>
                           </button>

                           <p class="description">
                              <?php esc_html_e( 'Upload a transparent PNG watermark. It will be baked into the images on single product pages (downloaded files will also have watermark).', 'woo-single-product-watermark' ); ?>
                           </p>
                       </td>
                   </tr>

                   <tr>
                       <th scope="row">
                          <?php esc_html_e( 'Position', 'woo-single-product-watermark' ); ?>
                       </th>
                       <td>
                           <fieldset>
                               <label>
                                   <input type="radio" name="wspw_watermark_position" value="center" <?php checked( $position, 'center' ); ?>>
                                  <?php esc_html_e( 'Center', 'woo-single-product-watermark' ); ?>
                               </label><br>
                               <label>
                                   <input type="radio" name="wspw_watermark_position" value="top_left" <?php checked( $position, 'top_left' ); ?>>
                                  <?php esc_html_e( 'Left top', 'woo-single-product-watermark' ); ?>
                               </label><br>
                               <label>
                                   <input type="radio" name="wspw_watermark_position" value="top_right" <?php checked( $position, 'top_right' ); ?>>
                                  <?php esc_html_e( 'Right top', 'woo-single-product-watermark' ); ?>
                               </label><br>
                               <label>
                                   <input type="radio" name="wspw_watermark_position" value="bottom_left" <?php checked( $position, 'bottom_left' ); ?>>
                                  <?php esc_html_e( 'Left bottom', 'woo-single-product-watermark' ); ?>
                               </label><br>
                               <label>
                                   <input type="radio" name="wspw_watermark_position" value="bottom_right" <?php checked( $position, 'bottom_right' ); ?>>
                                  <?php esc_html_e( 'Right bottom', 'woo-single-product-watermark' ); ?>
                               </label>
                           </fieldset>
                       </td>
                   </tr>

                   <tr>
                       <th scope="row">
                          <?php esc_html_e( 'Watermark size', 'woo-single-product-watermark' ); ?>
                       </th>
                       <td>
                           <fieldset>
                               <label>
                                   <input type="radio" name="wspw_watermark_size_mode" value="default" <?php checked( $size_mode, 'default' ); ?>>
                                  <?php esc_html_e( 'Default size (automatic)', 'woo-single-product-watermark' ); ?>
                               </label><br>
                               <label>
                                   <input type="radio" name="wspw_watermark_size_mode" value="custom" <?php checked( $size_mode, 'custom' ); ?>>
                                  <?php esc_html_e( 'Custom width (pixels)', 'woo-single-product-watermark' ); ?>
                               </label>
                               <input type="number" min="20" max="1000" name="wspw_watermark_custom_width_px" value="<?php echo esc_attr( $custom_width ); ?>" style="width:80px;">
                               <span class="description"><?php esc_html_e( 'Used only when "Custom width" is selected.', 'woo-single-product-watermark' ); ?></span>
                           </fieldset>
                       </td>
                   </tr>

                   <tr>
                       <th scope="row">
                          <?php esc_html_e( 'Max size for small photos', 'woo-single-product-watermark' ); ?>
                       </th>
                       <td>
                           <input type="number" min="5" max="100" name="wspw_watermark_max_width_pct" value="<?php echo esc_attr( $max_pct ); ?>" style="width:80px;">
                           <span class="description">
                                <?php esc_html_e( 'Maximum watermark width as % of image width (so it doesn’t cover tiny images).', 'woo-single-product-watermark' ); ?>
                            </span>
                       </td>
                   </tr>
                   </tbody>
               </table>

              <?php submit_button( __( 'Save changes', 'woo-single-product-watermark' ), 'primary', 'wspw_save_settings' ); ?>
           </form>
       </div>

       <script>
           (function($){
               $('#wspw-select-image').on('click', function(e){
                   e.preventDefault();

                   var frame = wp.media({
                       title: '<?php echo esc_js( __( 'Select watermark image', 'woo-single-product-watermark' ) ); ?>',
                       button: { text: '<?php echo esc_js( __( 'Use this image', 'woo-single-product-watermark' ) ); ?>' },
                       multiple: false
                   });

                   frame.on('select', function(){
                       var attachment = frame.state().get('selection').first().toJSON();
                       $('#wspw_watermark_attachment_id').val(attachment.id);
                       var url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
                       $('#wspw-watermark-preview').html('<img src="'+url+'" style="max-width:200px;height:auto;border:1px solid #ccc;padding:3px;background:#fff;">');
                   });

                   frame.open();
               });

               $('#wspw-remove-image').on('click', function(e){
                   e.preventDefault();
                   $('#wspw_watermark_attachment_id').val('');
                   $('#wspw-watermark-preview').html('<em><?php echo esc_js( __( 'No image selected.', 'woo-single-product-watermark' ) ); ?></em>');
               });
           })(jQuery);
       </script>
      <?php
   }

   /**
    * Filter image src only on single product pages.
    * Replaces image URL with watermarked cached version.
    */
   public function filter_image_src( $image, $attachment_id, $size, $icon ) {
      // Admin-ში საერთოდ არ ვამუშავებთ
      if ( is_admin() ) {
         return $image;
      }

      // მხოლოდ single product გვერდი
      if ( ! function_exists( 'is_product' ) || ! is_product() ) {
         return $image;
      }

      // URL არ არის? გავიდეთ
      if ( empty( $image[0] ) ) {
         return $image;
      }

      // თუ უკვე ჩვენი cache-დან მოდის, აღარ დავამუშაოთ თავიდან
      if ( strpos( $image[0], '/wspw-cache/' ) !== false ) {
         return $image;
      }

      // ვიმუშაოთ მხოლოდ იმ size-ებზე, რომლებიც მთავარ გალერიაშია
      // (თუ გინდა, შეგიძლია მოუმატო სხვა ზომებიც)
      if ( is_string( $size ) ) {
         $allowed_sizes = array(
            'woocommerce_single',          // მთავარი დიდი ფოტო
            'woocommerce_gallery_thumbnail', // Woo gallery thumbs (თუ იყენებ)
            'full',                        // ზოგ თემას შეუძლია full გამოიტანოს
         );

         if ( ! in_array( $size, $allowed_sizes, true ) ) {
            // მაგალითად „მსგავსი განცხადებების“ grid ხშირად სხვა size-ს იყენებს
            return $image;
         }
      }

      $attachment_id = (int) $attachment_id;
      if ( $attachment_id <= 0 ) {
         return $image;
      }

      /**
       * მთავარი ტრიუკი:
       * ვიღებთ იმ პროდუქტს, რომლის single გვერდზეც ვართ (queried object),
       * და watermark-ს ვადებთ მხოლოდ მის main + gallery სურათებზე,
       * მიუხედავად იმისა, რას აკეთებს global $product related loop-ებში.
       */
      $single_product_id = get_queried_object_id();
      if ( ! $single_product_id ) {
         return $image;
      }

      if ( ! function_exists( 'wc_get_product' ) ) {
         return $image;
      }

      $single_product = wc_get_product( $single_product_id );
      if ( ! $single_product instanceof WC_Product ) {
         return $image;
      }

      // ამ პროდუქტის main + gallery attachment ID-ები
      $valid_ids = array();

      $main_id = (int) $single_product->get_image_id();
      if ( $main_id ) {
         $valid_ids[] = $main_id;
      }

      $gallery_ids = $single_product->get_gallery_image_ids();
      if ( is_array( $gallery_ids ) && ! empty( $gallery_ids ) ) {
         foreach ( $gallery_ids as $gid ) {
            $valid_ids[] = (int) $gid;
         }
      }

      $valid_ids = array_unique( $valid_ids );

      // თუ ეს attachment არ არის ამ single პროდუქტის main/gallery-დან – ვერ ვეხებით
      if ( ! in_array( $attachment_id, $valid_ids, true ) ) {
         return $image;
      }

      // აქამდე რომ მოვედით, ზუსტად ვიცით, რომ ეს არის
      // მიმდინარე single პროდუქტის main/gallery ფოტო
      $opts = $this->get_options();
      if ( empty( $opts['attachment_id'] ) ) {
         return $image;
      }

      $original_url = $image[0];

      $watermarked_url = $this->maybe_generate_watermarked_image(
         $original_url,
         $attachment_id,
         $size,
         $opts
      );

      if ( $watermarked_url ) {
         $image[0] = $watermarked_url;
      }

      return $image;
   }



   /**
    * Generate (or reuse cached) watermarked image file and return its URL.
    * Original image file is NOT modified.
    */
   protected function maybe_generate_watermarked_image( $original_url, $attachment_id, $size, $opts ) {
      // Require GD
      if ( strpos( $original_url, '/wspw-cache/' ) !== false ) {
         return null;
      }

      // Require GD
      if ( ! function_exists( 'imagecreatefromstring' ) ) {
         return null;
      }

      $upload = wp_upload_dir();
      if ( ! isset( $upload['baseurl'], $upload['basedir'] ) ) {
         return null;
      }

      if ( strpos( $original_url, $upload['baseurl'] ) === false ) {
         // მხოლოდ uploads-დან
         return null;
      }

      // Map URL -> file path
      $relative = str_replace( $upload['baseurl'], '', $original_url );
      $original_path = $upload['basedir'] . $relative;

      $watermark_id = (int) $opts['attachment_id'];
      $watermark_url = wp_get_attachment_image_url( $watermark_id, 'full' );
      if ( ! $watermark_url || strpos( $watermark_url, $upload['baseurl'] ) === false ) {
         return null;
      }

      $wm_relative = str_replace( $upload['baseurl'], '', $watermark_url );
      $watermark_path = $upload['basedir'] . $wm_relative;

      if ( ! file_exists( $original_path ) || ! file_exists( $watermark_path ) ) {
         return null;
      }

      // Cache dir
      $cache_dir  = trailingslashit( $upload['basedir'] ) . 'wspw-cache';
      $cache_url  = trailingslashit( $upload['baseurl'] ) . 'wspw-cache';

      if ( ! is_dir( $cache_dir ) ) {
         wp_mkdir_p( $cache_dir );
      }

      $position     = $opts['position'];
      $size_mode    = $opts['size_mode'];
      $custom_width = (int) $opts['custom_width'];
      $max_pct      = (int) $opts['max_pct'];

      if ( $custom_width <= 0 ) {
         $custom_width = 150;
      }
      if ( $max_pct <= 0 || $max_pct > 100 ) {
         $max_pct = 40;
      }

      $hash_input = implode( '|', array(
         $original_path,
         $watermark_path,
         $position,
         $size_mode,
         $custom_width,
         $max_pct,
         filemtime( $original_path ),
         filemtime( $watermark_path ),
      ) );

      $hash = md5( $hash_input );

      $ext = strtolower( pathinfo( $original_path, PATHINFO_EXTENSION ) );
      if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true ) ) {
         // Only JPEG/PNG supported
         $ext = 'jpg';
      }

      $cache_filename = 'wspw-' . $attachment_id . '-' . sanitize_key( is_string( $size ) ? $size : 'size' ) . '-' . $hash . '.' . $ext;
      $cache_path     = trailingslashit( $cache_dir ) . $cache_filename;
      $cache_url_full = trailingslashit( $cache_url ) . $cache_filename;

      // If already generated, reuse
      if ( file_exists( $cache_path ) ) {
         return $cache_url_full;
      }

      // Generate new watermarked file
      if ( ! $this->generate_watermarked_file(
         $original_path,
         $watermark_path,
         $cache_path,
         $position,
         $size_mode,
         $custom_width,
         $max_pct
      ) ) {
         return null;
      }

      return $cache_url_full;
   }

   /**
    * Do the actual image processing using GD.
    * Returns true on success.
    */
   protected function generate_watermarked_file( $original_path, $watermark_path, $dest_path, $position, $size_mode, $custom_width, $max_pct ) {
      $original_data  = @file_get_contents( $original_path );
      $watermark_data = @file_get_contents( $watermark_path );

      if ( $original_data === false || $watermark_data === false ) {
         return false;
      }

      $orig_im = @imagecreatefromstring( $original_data );
      $wm_im   = @imagecreatefromstring( $watermark_data );

      if ( ! $orig_im || ! $wm_im ) {
         if ( $orig_im ) imagedestroy( $orig_im );
         if ( $wm_im ) imagedestroy( $wm_im );
         return false;
      }

      $orig_w = imagesx( $orig_im );
      $orig_h = imagesy( $orig_im );

      $wm_w = imagesx( $wm_im );
      $wm_h = imagesy( $wm_im );

      if ( $orig_w <= 0 || $orig_h <= 0 || $wm_w <= 0 || $wm_h <= 0 ) {
         imagedestroy( $orig_im );
         imagedestroy( $wm_im );
         return false;
      }

      // სწორად დავვალიდიროთ %
      if ( $max_pct <= 0 || $max_pct > 100 ) {
         $max_pct = 40;
      }

      /**
       * 1) ვპოულობთ "საბაზო" სიგანეს:
       *    - default → watermark-ის ორიგინალი სიგანე
       *    - custom  → Admin-ში მითითებული px
       */
      if ( $size_mode === 'default' ) {
         $base_width_px = $wm_w;
      } else {
         if ( $custom_width <= 0 ) {
            $custom_width = 150;
         }
         $base_width_px = $custom_width;
      }

      // 2) საბაზო სიგანე ვერ იქნება ფოტოზე ფართო
      if ( $base_width_px > $orig_w ) {
         $base_width_px = $orig_w;
      }

      /**
       * 3) "პატარა ფოტოს" ლიმიტი:
       *    თუ base_width_px > max_pct% ფოტოს სიგანის,
       *    значи ფოტო პატარაა და სიგანე იქნება ზუსტად max_pct%
       */
      if ( $orig_w < 300 ) {
         // watermark-ის სიგანე იქნება max_pct%
         $target_w = (int) round( $orig_w * ( $max_pct / 100 ) );
      } else {
         // დიდი ფოტო — ვიყენებთ base_width (default/custom) ზომას
         $target_w = (int) $base_width_px;
      }

// უსაფრთხო მინიმუმი
      if ( $target_w < 20 ) {
         $target_w = 20;
      }

      // სიმაღლე პროპორციულად
      $scale    = $target_w / $wm_w;
      $target_h = (int) round( $wm_h * $scale );

      if ( $target_w <= 0 || $target_h <= 0 ) {
         imagedestroy( $orig_im );
         imagedestroy( $wm_im );
         return false;
      }

      // Resize watermark
      $wm_resized = imagecreatetruecolor( $target_w, $target_h );
      imagealphablending( $wm_resized, false );
      imagesavealpha( $wm_resized, true );
      imagecopyresampled(
         $wm_resized,
         $wm_im,
         0, 0, 0, 0,
         $target_w, $target_h,
         $wm_w, $wm_h
      );

      // Prepare original (preserve alpha for PNG)
      imagealphablending( $orig_im, true );
      imagesavealpha( $orig_im, true );

      $margin = 10;

      switch ( $position ) {
         case 'top_left':
            $dst_x = $margin;
            $dst_y = $margin;
            break;
         case 'top_right':
            $dst_x = $orig_w - $target_w - $margin;
            $dst_y = $margin;
            break;
         case 'bottom_left':
            $dst_x = $margin;
            $dst_y = $orig_h - $target_h - $margin;
            break;
         case 'bottom_right':
            $dst_x = $orig_w - $target_w - $margin;
            $dst_y = $orig_h - $target_h - $margin;
            break;
         case 'center':
         default:
            $dst_x = (int) round( ( $orig_w - $target_w ) / 2 );
            $dst_y = (int) round( ( $orig_h - $target_h ) / 2 );
            break;
      }

      if ( $dst_x < 0 ) $dst_x = 0;
      if ( $dst_y < 0 ) $dst_y = 0;

      // Overlay
      imagecopy(
         $orig_im,
         $wm_resized,
         $dst_x,
         $dst_y,
         0,
         0,
         $target_w,
         $target_h
      );

      // Save
      $ext = strtolower( pathinfo( $dest_path, PATHINFO_EXTENSION ) );

      // Ensure directory exists
      $dest_dir = dirname( $dest_path );
      if ( ! is_dir( $dest_dir ) ) {
         wp_mkdir_p( $dest_dir );
      }

      $saved = false;

      if ( $ext === 'png' ) {
         imagealphablending( $orig_im, false );
         imagesavealpha( $orig_im, true );
         $saved = imagepng( $orig_im, $dest_path, 6 );
      } else {
         // default jpeg (მუშაობს jpg / jpeg ორივეზე)
         $saved = imagejpeg( $orig_im, $dest_path, 90 );
      }

      imagedestroy( $orig_im );
      imagedestroy( $wm_im );
      imagedestroy( $wm_resized );

      return (bool) $saved;
   }

}

new WSPW_Single_Product_Watermark();