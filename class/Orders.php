<?php
/**
 * Orders class to perform all actions on the order via Arrow Checkout
 */
if ( ! class_exists( 'Orders' ) ) {
	class Orders {

		/**
		 * @var string[]
		 */
		public $archivedStatus = [ 'status' => 'wc-archived', 'label' => 'Archived' ];

		/**
		 * @var array
		 */
		public $pendingStatus = [ 'status' => 'wc-pending', 'label' => 'Pending payment' ];

		/**
		 * @var string
		 */
		private $pageTitle = 'Archived Orders';

		/**
		 * @var string
		 */
		private $menuTitle = 'Archived Orders';

		/**
		 * @var string
		 */
		private $capability = 'manage_options';

		/**
		 * @var string
		 */
		private $menuSlug = 'archived-orders';

		/**
		 * @var string
		 */
		private $postType = 'shop_order';

		/**
		 * @var string
		 */
		public $metaValue = 'arrow';

		/**
		 *
		 */
		public function __construct() {
			add_filter( 'wc_order_statuses', [ $this, 'addArchivedToOrderStatuses' ] );

			if ( is_admin() ) {
				add_action( 'admin_menu', [ $this, 'createArchivedOrderMenu' ] );
			}

			add_action( 'parse_query', [ $this, 'hideArchivedOrders' ] );
		}

		/**
		 * @param $status
		 * @param $label
		 *
		 * @return void
		 */
		public function registerStatus( $status, $label ) {
			register_post_status( $status, [
				'show_in_admin_status_list' => true,
				'show_in_admin_all_list'    => true,
				'exclude_from_search'       => false,
				'label'                     => $label,
				'public'                    => true,
			] );
		}

		/**
		 * @param $statuses
		 *
		 * @return array
		 */
		public function addArchivedToOrderStatuses( $statuses ) {
			$new_order_statuses = array();

			foreach ( $statuses as $key => $status ) {
				$new_order_statuses[ $key ] = $status;
			}
			if ( ! key_exists( $this->archivedStatus['status'], $new_order_statuses ) ) {
				$new_order_statuses[ $this->archivedStatus['status'] ] = $this->archivedStatus['label'];
			}

			return $new_order_statuses;
		}

		/**
		 * @return void
		 */
		public function createArchivedOrderMenu() {
			add_submenu_page( 'woocommerce', $this->pageTitle, $this->menuTitle, $this->capability, $this->menuSlug, [
				$this,
				'generateArchivedOrdersPage'
			] );
		}


		public function generateArchivedOrdersPage() {
			$args = array(
				'post_type'      => $this->postType,
				'post_status'    => $this->archivedStatus['status'],
				'posts_per_page' => - 1
			);

			$query = new WP_Query( $args );

			if ( $query->have_posts() ):
				$html = '<table class="wp-list-table widefat fixed striped table-view-list posts">
                <thead>
                    <tr>
                        <th class="manage-column column-order_number column-primary">Order</th>
                        <th class="manage-column column-order_number column-primary">Date</th>
                        <th class="manage-column column-order_number column-primary">Status</th>
                        <th class="manage-column column-order_number column-primary">Total</th>
                    </tr>
                </thead>
                <tbody>
                ';
				while ( $query->have_posts() ) : $query->the_post();

					$order = wc_get_order( $query->post->ID );
					$html  .= '<tr>
                    <td class="order_number column-order_number has-row-actions column-primary">' . $order->get_user()->data->display_name . '</td>
                    <td class="order_date column-order_date">' . $order->get_date_created() . '</td>
                    <td class="order_status column-order_status">' . $order->get_status() . '</td>
                    <td class="order_total column-order_total">' . $order->get_formatted_order_total() . '</td>
                </tr>';

				endwhile;
				$html .= '</tbody></table>';
				echo paginate_links( $args );
				echo $html;
				wp_reset_postdata();
			endif;

		}

		/**
		 * @param $status
		 * @param $metaValue
		 *
		 * @return void
		 */
		public function updateOrders( $status, $metaValue ) {
			global $wpdb;

			$maxDays = get_option( 'woocommerce_arrow_settings' )['max_order_age'];
			if ( empty( $maxDays ) ) {
				$maxDays = 2; //default days to archive the orders
			}

			$maxAge = date( 'Y-m-d H:i:s', strtotime( "-$maxDays days" ) );

			//select the orders
			$sqlSelect = "SELECT p.ID FROM $wpdb->posts p left join $wpdb->postmeta pm on p.ID = pm.post_id 
            where p.post_date < '$maxAge' AND p.post_status = '$status' AND p.post_type = 'shop_order' AND pm.meta_value='$metaValue'";


			$orders = $wpdb->get_results( $sqlSelect );

			if ( ! empty( $orders ) ) {
				$orderIDs = [];
				foreach ( $orders as $order ) {
					$orderIDs[] = $order->ID;
					$this->addOrderNote( $order->ID, 'Order Status changed from Pending to Archived via Cron' );
				}

				$implodeIds = implode( ',', $orderIDs );

				$sqlUpdate = "UPDATE $wpdb->posts p SET p.post_status = 'wc-archived' WHERE p.ID IN ($implodeIds)";
				$wpdb->query( $sqlUpdate );

			}

		}

		/**
		 * @param $order_id
		 * @param $note
		 *
		 * @return false|int
		 */
		private function addOrderNote( $order_id, $note ) {
			$commentdata = apply_filters( 'woocommerce_new_order_note_data',
				array(
					'comment_post_ID'      => $order_id,
					'comment_author'       => __( 'WooCommerce', 'woo-archive-orders' ),
					'comment_author_email' => strtolower( __( 'WooCommerce', 'woo-archive-orders' ) ) . '@' . site_url(),
					'comment_author_url'   => '',
					'comment_content'      => $note,
					'comment_agent'        => 'WooCommerceArchiveOrder',
					'comment_type'         => 'order_note',
					'comment_parent'       => 0,
					'comment_approved'     => 1,
				),
				array(
					'order_id'         => $order_id,
					'is_customer_note' => false,
				)
			);

			return wp_insert_comment( $commentdata );
		}

		public function hideArchivedOrders( $query ) {
			global $pagenow;

			$hide_order_status = $this->archivedStatus['status'];

			$query_vars = &$query->query_vars;

			if ( $pagenow == 'edit.php' && $query_vars['post_type'] == $this->postType ) {

				if ( is_array( $query_vars['post_status'] ) ) {
					if ( ( $key = array_search( $hide_order_status, $query_vars['post_status'] ) ) !== false ) {
						unset( $query_vars['post_status'][ $key ] );
					}
				}
			}

		}

	}


}
