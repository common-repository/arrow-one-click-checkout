<?php

/**
 * Products class to perform all products related functions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Products' ) ) {
	class Products {

		/**
		 * @var string[]
		 */
		private $acceptedTypes = [ 'simple', 'grouped', 'variable' ];


		/**
		 * @var string
		 */
		private $defaultSortOrder = 'ASC';

		/**
		 * @var int
		 */
		private $successStatus = 200;

		/**
		 * @var int
		 */
		private $defaultPrice = 0;

		/**
		 * @var string
		 */
		private $sortBy = 'ID';

		/**
		 * @var string
		 */
		private $status = 'publish';


		/**
		 * @return WP_REST_Response
		 */
		public function getAllProducts() {

			$limit     = ( ( isset( $_GET['limit'] ) ) && ( ! empty( $_GET['limit'] ) ) && ( preg_match( '/^\d+$/', $_GET['limit'] ) ) ) ? $_GET['limit'] : 10;
			$page      = ( ( isset( $_GET['page'] ) ) && ( ! empty( $_GET['page'] ) ) && ( preg_match( '/^\d+$/', $_GET['page'] ) ) ) ? $_GET['page'] : 1;
			$sortOrder = ( ! empty( $_GET['sort_order'] ) ) ? $_GET['sort_order'] : $this->defaultSortOrder;

			$data = array();
			$args = array(
				'paginate' => true,
				'order_by' => $this->sortBy,
				'status'   => $this->status,
				'limit'    => $limit,
				'order'    => $sortOrder,
				'page'     => $page,
			);

			$products = wc_get_products( $args );

			foreach ( $products->products as $product ) {

				if ( in_array( $product->get_type(), $this->acceptedTypes ) ) {
					$data['data'][] = [
						'id'                 => $product->get_id(),
						'name'               => $product->get_name(),
						'slug'               => $product->get_slug(),
						'permalink'          => $product->get_permalink(),
						'date_created'       => $product->get_date_created(),
						'date_modified'      => $product->get_date_modified(),
						'type'               => $product->get_type(),
						'status'             => $product->get_status(),
						'featured'           => $product->get_featured(),
						'catalog_visibility' => $product->get_catalog_visibility(),
						'description'        => $product->get_description(),
						'short_description'  => $product->get_short_description(),
						'sku'                => $product->get_sku(),
						'price'              => ( $product->get_price() === "" ) ? $this->defaultPrice : $product->get_price(),
						'regular_price'      => ( $product->get_regular_price() === "" ) ? $this->defaultPrice : $product->get_regular_price(),
						'sale_price'         => ( $product->get_sale_price() === "" ) ? $this->defaultPrice : $product->get_sale_price(),
						'date_on_sale_from'  => $product->get_date_on_sale_from(),
						'date_on_sale_to'    => $product->get_date_on_sale_to(),
						'price_html'         => $product->get_price_html(),
						'on_sale'            => $product->is_on_sale(),
						'purchasable'        => $product->is_purchasable(),
						'total_sales'        => $product->get_total_sales(),
						'virtual'            => $product->is_virtual(),
						'downloadable'       => $product->is_downloadable(),
						'downloads'          => $product->get_downloads(),
						'download_limit'     => $product->get_download_limit(),
						'download_expiry'    => $product->get_download_expiry(),
						'tax_status'         => $product->get_tax_status(),
						'tax_class'          => $product->get_tax_class(),
						'manage_stock'       => $product->get_manage_stock(),
						'stock_quantity'     => $product->get_stock_quantity(),
						'stock_status'       => $product->get_stock_status(),
						'backorders'         => $product->get_backorders(),
						'backorders_allowed' => $product->backorders_allowed(),
						'backordered'        => $product->is_on_backorder(),
						'sold_individually'  => $product->get_sold_individually(),
						'weight'             => $product->get_weight(),
						'dimensions'         => $product->get_dimensions(),
						'shipping_required'  => $product->needs_shipping(),
						'shipping_taxable'   => $product->is_shipping_taxable(),
						'shipping_class'     => $product->get_shipping_class(),
						'shipping_class_id'  => ( 0 !== $product->get_shipping_class_id() ) ? $product->get_shipping_class_id() : null,
						'reviews_allowed'    => $product->get_reviews_allowed(),
						'average_rating'     => wc_format_decimal( $product->get_average_rating(), 2 ),
						'rating_count'       => $product->get_rating_count(),
						'related_ids'        => array_map( 'absint', array_values( wc_get_related_products( $product->get_id() ) ) ),
						'upsell_ids'         => array_map( 'absint', $product->get_upsell_ids() ),
						'cross_sell_ids'     => array_map( 'absint', $product->get_cross_sell_ids() ),
						'parent_id'          => $product->get_parent_id(),
						'purchase_note'      => apply_filters( 'the_content', $product->get_purchase_note() ),
						'categories'         => wc_get_object_terms( $product->get_id(), 'product_cat' ),
						'tags'               => wc_get_object_terms( $product->get_id(), 'product_tag' ),
						'images'             => $this->getImages( $product ),
						'attributes'         => $this->getAttributes( $product ),
						'default_attributes' => $product->get_default_attributes(),
						'variations'         => $this->getVariationData( $product ),
						'grouped_products'   => $product->get_children(),
						'menu_order'         => $product->get_menu_order(),
						'meta_data'          => $product->get_meta_data()
					];
				}
			}

			$data['current_page'] = $page;
			$data['per_page']     = $limit;
			$data['total']        = $products->max_num_pages;

			return new WP_REST_Response( $data, $this->successStatus );
		}


		/**
		 * @param $product
		 *
		 * @return array
		 */
		private function getVariationData( $product ) {
			$variations = array();

			foreach ( $product->get_children() as $child_id ) {
				$variation = wc_get_product( $child_id );

				if ( ! $variation || ! $variation->exists() ) {
					continue;
				}

				$variations[] = array(
					'id'                 => $variation->get_id(),
					'created_at'         => $variation->get_date_created(),
					'updated_at'         => $variation->get_date_modified(),
					'downloadable'       => $variation->is_downloadable(),
					'virtual'            => $variation->is_virtual(),
					'permalink'          => $variation->get_permalink(),
					'sku'                => $variation->get_sku(),
					'price'              => ($variation->get_price() === "") ? $this->defaultPrice : $variation->get_price(),
					'regular_price'      => ($variation->get_regular_price() === "") ? $this->defaultPrice : $variation->get_regular_price(),
					'sale_price'         => ($variation->get_sale_price() === "") ? $this->defaultPrice : $variation->get_sale_price(),
					'taxable'            => $variation->is_taxable(),
					'tax_status'         => $variation->get_tax_status(),
					'tax_class'          => $variation->get_tax_class(),
					'managing_stock'     => $variation->managing_stock(),
					'stock_quantity'     => $variation->get_stock_quantity(),
					'in_stock'           => $variation->is_in_stock(),
					'backorders_allowed' => $variation->backorders_allowed(),
					'backordered'        => $variation->is_on_backorder(),
					'purchaseable'       => $variation->is_purchasable(),
					'visible'            => $variation->is_visible(),
					'on_sale'            => $variation->is_on_sale(),
					'weight'             => $variation->get_weight() ? $variation->get_weight() : null,
					'dimensions'         => array(
						'length' => $variation->get_length(),
						'width'  => $variation->get_width(),
						'height' => $variation->get_height(),
						'unit'   => get_option( 'woocommerce_dimension_unit' ),
					),
					'shipping_class'     => $variation->get_shipping_class(),
					'shipping_class_id'  => ( 0 !== $variation->get_shipping_class_id() ) ? $variation->get_shipping_class_id() : null,
					'image'              => $this->getImages( $variation ),
					'attributes'         => $this->getAttributes( $variation ),
					'downloads'          => $variation->get_downloads(),
					'download_limit'     => (int) $product->get_download_limit(),
					'download_expiry'    => (int) $product->get_download_expiry(),
				);
			}

			return $variations;
		}

		/**
		 * @param $product
		 *
		 * @return array
		 */
		private function getImages( $product ) {
			$images        = $attachment_ids = array();
			$product_image = $product->get_image_id();

			// Add featured image.
			if ( ! empty( $product_image ) ) {
				$attachment_ids[] = $product_image;
			}

			// Add gallery images.
			$attachment_ids = array_merge( $attachment_ids, $product->get_gallery_image_ids() );

			// Build image data.
			foreach ( $attachment_ids as $position => $attachment_id ) {

				$attachment_post = get_post( $attachment_id );

				if ( is_null( $attachment_post ) ) {
					continue;
				}

				$attachment = wp_get_attachment_image_src( $attachment_id, 'full' );

				if ( ! is_array( $attachment ) ) {
					continue;
				}

				$images[] = array(
					'id'         => (int) $attachment_id,
					'created_at' => $attachment_post->post_date_gmt,
					'updated_at' => $attachment_post->post_modified_gmt,
					'src'        => current( $attachment ),
					'title'      => get_the_title( $attachment_id ),
					'alt'        => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
					'position'   => (int) $position,
				);
			}

			// Set a placeholder image if the product has no images set.
			if ( empty( $images ) ) {

				$images[] = array(
					'id'         => 0,
					'created_at' => time(), // Default to now.
					'updated_at' => time(),
					'src'        => wc_placeholder_img_src(),
					'title'      => __( 'Placeholder', 'woocommerce' ),
					'alt'        => __( 'Placeholder', 'woocommerce' ),
					'position'   => 0,
				);
			}

			return $images;
		}


		/**
		 * @param $product
		 *
		 * @return array
		 */
		private function getAttributes( $product ) {

			$attributes = array();

			if ( $product->is_type( 'variation' ) ) {

				// variation attributes
				foreach ( $product->get_variation_attributes() as $attribute_name => $attribute ) {

					// taxonomy-based attributes are prefixed with `pa_`, otherwise simply `attribute_`
					$attributes[] = array(
						'name'   => wc_attribute_label( str_replace( 'attribute_', '', $attribute_name ), $product ),
						'slug'   => str_replace( 'attribute_', '', wc_attribute_taxonomy_slug( $attribute_name ) ),
						'option' => $attribute,
					);
				}
			} else {

				foreach ( $product->get_attributes() as $attribute ) {
					$attributes[] = array(
						'name'      => wc_attribute_label( $attribute['name'], $product ),
						'slug'      => wc_attribute_taxonomy_slug( $attribute['name'] ),
						'position'  => (int) $attribute['position'],
						'visible'   => (bool) $attribute['is_visible'],
						'variation' => (bool) $attribute['is_variation'],
						'options'   => $this->getAttributeOptions( $product->get_id(), $attribute ),
					);
				}
			}

			return $attributes;
		}

		/**
		 * @param $product_id
		 * @param $attribute
		 *
		 * @return array
		 */
		private function getAttributeOptions( $product_id, $attribute ) {
			if ( isset( $attribute['is_taxonomy'] ) && $attribute['is_taxonomy'] ) {
				return wc_get_product_terms( $product_id, $attribute['name'], array( 'fields' => 'names' ) );
			} elseif ( isset( $attribute['value'] ) ) {
				return array_map( 'trim', explode( '|', $attribute['value'] ) );
			}

			return array();
		}
	}
}
