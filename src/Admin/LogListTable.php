<?php
/**
 * Log list table.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Admin;

use PostVoyage\Log\Logger;
use PostVoyage\Networks\Registry;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * WP_List_Table for log entries.
 */
final class LogListTable extends \WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'postvoyage-log-entry',
				'plural'   => 'postvoyage-log-entries',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array<string,string>
	 */
	public function get_columns() {
		return array(
			'created_at' => __( 'Date', 'postvoyage-socialmedia-poster' ),
			'post'       => __( 'Post', 'postvoyage-socialmedia-poster' ),
			'account'    => __( 'Account', 'postvoyage-socialmedia-poster' ),
			'status'     => __( 'Status', 'postvoyage-socialmedia-poster' ),
			'message'    => __( 'Message', 'postvoyage-socialmedia-poster' ),
		);
	}

	/**
	 * Loads the items.
	 */
	public function prepare_items() {
		$per_page = 30;
		$data     = Logger::query(
			array(
				'status'   => $this->current_status(),
				'per_page' => $per_page,
				'page'     => $this->get_pagenum(),
			)
		);

		$this->items           = $data['rows'];
		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$this->set_pagination_args(
			array(
				'total_items' => $data['total'],
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Status filter links.
	 *
	 * @return array<string,string>
	 */
	protected function get_views() {
		$base    = admin_url( 'admin.php?page=' . LogPage::SLUG );
		$current = $this->current_status();
		$views   = array(
			''        => __( 'All', 'postvoyage-socialmedia-poster' ),
			'success' => __( 'Successful', 'postvoyage-socialmedia-poster' ),
			'error'   => __( 'Errors', 'postvoyage-socialmedia-poster' ),
		);
		$links   = array();
		foreach ( $views as $status => $label ) {
			$url                                       = '' === $status ? $base : add_query_arg( 'status', $status, $base );
			$links[ '' === $status ? 'all' : $status ] = sprintf(
				'<a href="%1$s"%2$s>%3$s</a>',
				esc_url( $url ),
				$current === $status ? ' class="current" aria-current="page"' : '',
				esc_html( $label )
			);
		}
		return $links;
	}

	/**
	 * Message when empty.
	 */
	public function no_items() {
		esc_html_e( 'Nothing shared yet.', 'postvoyage-socialmedia-poster' );
	}

	/**
	 * Date column.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	protected function column_created_at( $item ) {
		$timestamp = strtotime( $item->created_at . ' UTC' );
		return esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) );
	}

	/**
	 * Post column.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	protected function column_post( $item ) {
		$title = get_the_title( (int) $item->post_id );
		$link  = get_edit_post_link( (int) $item->post_id );
		if ( ! $link ) {
			return esc_html( '' !== $title ? $title : '#' . (int) $item->post_id );
		}
		return '<a href="' . esc_url( $link ) . '">' . esc_html( '' !== $title ? $title : '#' . (int) $item->post_id ) . '</a>';
	}

	/**
	 * Account column.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	protected function column_account( $item ) {
		$network = Registry::get( $item->network );
		return esc_html( $item->account_label ) . '<br><span class="postvoyage-muted">' . esc_html( $network ? $network->label() : $item->network ) . '</span>';
	}

	/**
	 * Status column.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	protected function column_status( $item ) {
		$ok = 'success' === $item->status;
		return sprintf(
			'<span class="postvoyage-status postvoyage-status--%1$s">%2$s</span>',
			$ok ? 'success' : 'error',
			$ok ? esc_html__( 'Shared', 'postvoyage-socialmedia-poster' ) : esc_html__( 'Error', 'postvoyage-socialmedia-poster' )
		);
	}

	/**
	 * Message column.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	protected function column_message( $item ) {
		$out = esc_html( $item->message );
		if ( '' !== $item->remote_url ) {
			$out .= ( '' !== $out ? ' ' : '' ) . '<a href="' . esc_url( $item->remote_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View post', 'postvoyage-socialmedia-poster' ) . ' &nearr;</a>';
		}
		return $out;
	}

	/**
	 * Current status filter.
	 *
	 * @return string
	 */
	private function current_status() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		return in_array( $status, array( 'success', 'error' ), true ) ? $status : '';
	}
}
