<?php namespace AWSWooCommerce;

use AWSWooCommerce\Hooks;
use AWSWooCommerce\GenericEvent;

class Plugin {
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	public function init() {
		if ( class_exists( '\WC_Integration' ) ) {
			include_once 'Hooks.php';
			include_once 'GenericEvent.php';
			include_once 'SNSEvent.php';
			include_once 'SQSEvent.php';
			include_once 'KinesisEvent.php';
			include_once 'FirehoseEvent.php';
			include_once 'S3Event.php';

			$hooks = new Hooks( array( $this, 'publish' ) );

			add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );
			do_action( 'aws_sns_woocommerce_initialized', $hooks, $settings );

			$this->register_rma_processing_order_status();
			add_filter( 'wc_order_statuses', array( $this, 'add_rma_processing_to_order_statuses' ) );
			$this->register_rma_canceled_order_status();
			add_filter( 'wc_order_statuses', array( $this, 'add_rma_canceled_to_order_statuses' ) );

			$this->settings = Settings::instance();
			add_option( 'rma_period_length', $this->settings->get_option( 'rma_period_length' ) );
		}
	}

	public function publish( $target, $event, $data, $timestamp = null ) {
		$target = apply_filters( 'aws_publish_event_target', $target, $event, $data, $timestamp );
		$event  = apply_filters( 'aws_publish_event_name', $event, $target, $data, $timestamp );
		$data   = apply_filters( 'aws_publish_event_data', $data, $target, $event, $timestamp );

		$timestamp = isset( $timestamp ) ? $timestamp : gmdate( 'c' );
		$timestamp = apply_filters( 'aws_publish_event_timestamp', $timestamp, $target, $event, $data );

		$e = new GenericEvent( $target, $event, $data, $timestamp );
		$e->publish();
	}

	public function add_integration( $integrations ) {
		$integrations[] = Settings::class;
		return $integrations;
	}

 	private function register_rma_processing_order_status() {
		register_post_status( 'wc-rma_processing', array(
			'label'                     => 'RMA processing',
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: custom status label */
			'label_count'               => _n_noop( 'RMA processing (%s)', 'RMA processing (%s)', 'woocommerce-aws-integration' ),
		) );
	}

	// Add to list of WC Order statuses
	public function add_rma_processing_to_order_statuses( $order_statuses ) {
		$new_order_statuses = array();

		// add rma-processing order status after processing
		foreach ( $order_statuses as $key => $status ) {

			$new_order_statuses[ $key ] = $status;

			if ( 'wc-processing' === $key ) {
				$new_order_statuses['wc-rma_processing'] = 'RMA processing';
			}
		}

		return $new_order_statuses;
	}

	private function register_rma_canceled_order_status() {
		register_post_status( 'wc-rma_canceled', array(
			'label'                     => 'RMA canceled',
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: custom status label */
			'label_count'               => _n_noop( 'RMA canceled (%s)', 'RMA canceled (%s)', 'woocommerce-aws-integration' ),
		) );
	}

	// Add to list of WC Order statuses
	public function add_rma_canceled_to_order_statuses( $order_statuses ) {
		$new_order_statuses = array();

		// add rma-canceled order status after rma_processing
		foreach ( $order_statuses as $key => $status ) {

			$new_order_statuses[ $key ] = $status;

			if ( 'wc-rma_processing' === $key ) {
				$new_order_statuses['wc-rma_canceled'] = 'RMA canceled';
			}
		}

		return $new_order_statuses;
	}

}

new Plugin( __FILE__ );
