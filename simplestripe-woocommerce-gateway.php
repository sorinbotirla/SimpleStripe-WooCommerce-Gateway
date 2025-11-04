<?php
/*
Plugin Name: SimpleStripe WooCommerce Gateway
Description: Lightweight WooCommerce payment gateway that redirects customers to Stripe Checkout and handles webhooks.
Version: 1.0.0
Author: Sorin Botirla
Text Domain: simplestripe
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Register gateway with WooCommerce (classic checkout)
add_filter( 'woocommerce_payment_gateways', function ( $methods ) {
    $methods[] = 'WC_Gateway_SimpleStripe';
    return $methods;
} );

add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }

    class WC_Gateway_SimpleStripe extends WC_Payment_Gateway {

        public $testmode;
        public $test_secret_key;
        public $live_secret_key;
        public $secret_key;
        public $autoload_path;
        public $currency;
        public $webhook_secret;
        public $supported_currencies = array( 'RON', 'EUR', 'USD' );

        public function __construct() {
            $this->id                 = 'simplestripe';
            $this->method_title       = __( 'Pay by Card (Stripe)', 'simplestripe' );
            $this->method_description = __( 'Redirect customers to Stripe Checkout using Stripe PHP SDK.', 'simplestripe' );
            $this->has_fields         = false;
            $this->supports           = array( 'products', 'refunds' );

            $this->init_form_fields();
            $this->init_settings();

            $this->title          = $this->get_option( 'title' );
            $this->description    = $this->get_option( 'description' );
            $this->testmode       = 'yes' === $this->get_option( 'testmode' );
            $this->test_secret_key  = $this->get_option( 'test_secret_key' );
            $this->live_secret_key  = $this->get_option( 'live_secret_key' );
            $this->autoload_path    = $this->get_option( 'autoload_path' );
            $this->webhook_secret   = $this->get_option( 'webhook_secret' );
            $this->secret_key       = $this->testmode ? $this->test_secret_key : $this->live_secret_key;
            $this->currency         = get_woocommerce_currency();

            $this->initialize_hooks();

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        }

        public function initialize_hooks() {
            // Support for WooCommerce Blocks
            add_filter(
                'woocommerce_blocks_payment_method_type_registration_data',
                function ( $data, $payment_method_name ) {
                    if ( $payment_method_name === $this->id ) {
                        $data['supports'][] = 'products';
                    }
                    return $data;
                },
                10,
                2
            );
        }

        public function is_available() {
            if ( 'yes' !== $this->get_option( 'enabled' ) ) {
                return false;
            }
            if ( empty( $this->secret_key ) ) {
                return false;
            }
            if ( ! in_array( $this->currency, $this->supported_currencies, true ) ) {
                return false;
            }
            if ( empty( $this->autoload_path ) || ! file_exists( $this->autoload_path ) ) {
                return false;
            }
            return true;
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'   => __( 'Enable/Disable', 'simplestripe' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable Stripe card payments', 'simplestripe' ),
                    'default' => 'yes',
                ),
                'title' => array(
                    'title'   => __( 'Title', 'simplestripe' ),
                    'type'    => 'text',
                    'default' => __( 'Pay by card', 'simplestripe' ),
                ),
                'description' => array(
                    'title'       => __( 'Description', 'simplestripe' ),
                    'type'        => 'textarea',
                    'description' => __( 'Optional text shown under the payment method on the checkout page.', 'simplestripe' ),
                    'default'     => '',
                ),
                'testmode' => array(
                    'title'       => __( 'Test mode', 'simplestripe' ),
                    'type'        => 'checkbox',
                    'label'       => __( 'Enable test mode', 'simplestripe' ),
                    'default'     => 'yes',
                    'description' => __( 'If enabled, the test secret key will be used.', 'simplestripe' ),
                ),
                'test_secret_key' => array(
                    'title'       => __( 'Test secret key', 'simplestripe' ),
                    'type'        => 'password',
                    'description' => __( 'Enter your Stripe test secret key (sk_test_...)', 'simplestripe' ),
                    'default'     => '',
                ),
                'live_secret_key' => array(
                    'title'       => __( 'Live secret key', 'simplestripe' ),
                    'type'        => 'password',
                    'description' => __( 'Enter your Stripe live secret key (sk_live_...)', 'simplestripe' ),
                    'default'     => '',
                ),
                'webhook_secret' => array(
                    'title'       => __( 'Webhook signing secret', 'simplestripe' ),
                    'type'        => 'password',
                    'description' => __( 'Enter the endpoint secret from your Stripe dashboard (e.g. whsec_...). Used to validate webhooks.', 'simplestripe' ),
                    'default'     => '',
                ),
                'autoload_path' => array(
                    'title'       => __( 'Path to vendor/autoload.php', 'simplestripe' ),
                    'type'        => 'text',
                    'description' => __( 'Absolute path to the Stripe PHP SDK autoload file.', 'simplestripe' ),
                    'default'     => '',
                ),
            );
        }

        public function process_payment( $order_id ) {
            if ( ! file_exists( $this->autoload_path ) ) {
                wc_add_notice( __( 'Stripe SDK autoload.php not found. Please check the plugin settings.', 'simplestripe' ), 'error' );
                return array( 'result' => 'fail' );
            }

            require_once $this->autoload_path;
            \Stripe\Stripe::setApiKey( $this->secret_key );

            $order    = wc_get_order( $order_id );
            $amount   = intval( round( $order->get_total() * 100 ) );
            $currency = strtolower( $order->get_currency() );

            try {
                $session = \Stripe\Checkout\Session::create(
                    array(
                        'payment_method_types' => array( 'card' ),
                        'line_items'           => array(
                            array(
                                'price_data' => array(
                                    'currency'     => $currency,
                                    'product_data' => array(
                                        'name' => sprintf( __( 'Order #%d', 'simplestripe' ), $order_id ),
                                    ),
                                    'unit_amount'  => $amount,
                                ),
                                'quantity'   => 1,
                            ),
                        ),
                        'mode'               => 'payment',
                        'customer_email'     => $order->get_billing_email(),
                        'success_url'        => $this->get_return_url( $order ) . '&session_id={CHECKOUT_SESSION_ID}',
                        'cancel_url'         => $order->get_cancel_order_url(),
                        'metadata'           => array(
                            'order_id'      => $order_id,
                            'billing_email' => $order->get_billing_email(),
                        ),
                    )
                );

                return array(
                    'result'   => 'success',
                    'redirect' => $session->url,
                );
            } catch ( \Stripe\Exception\ApiErrorException $e ) {
                wc_add_notice( __( 'Stripe error: ', 'simplestripe' ) . $e->getMessage(), 'error' );
                return array( 'result' => 'fail' );
            }
        }
    }

    // WooCommerce Blocks Payment Method registration
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function ( $registry ) {
            if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
                class SimpleStripe_Blocks_Support extends Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {
                    protected $name = 'simplestripe';

                    public function initialize() {
                        $this->settings = get_option( 'woocommerce_simplestripe_settings', array() );
                    }

                    public function is_active() {
                        $gateway = new WC_Gateway_SimpleStripe();
                        return $gateway->is_available();
                    }

                    public function get_payment_method_data() {
                        $title       = ! empty( $this->settings['title'] ) ? $this->settings['title'] : __( 'Pay by card', 'simplestripe' );
                        $description = ! empty( $this->settings['description'] ) ? $this->settings['description'] : '';
                        return array(
                            'title'       => $title,
                            'description' => $description,
                            'supports'    => array( 'products' ),
                        );
                    }
                }
                $registry->register( new SimpleStripe_Blocks_Support() );
            }
        }
    );
} );

// Enqueue JS for checkout blocks
add_action( 'enqueue_block_assets', function () {
    if ( function_exists( 'is_checkout' ) && is_checkout() ) {
        wp_enqueue_script(
            'simplestripe-blocks',
            plugin_dir_url( __FILE__ ) . 'assets/js/blocks.js',
            array( 'wc-blocks-registry', 'wp-element' ),
            '1.0.0',
            true
        );
    }
} );

// Thank you page: verify Stripe session and mark order paid
add_action( 'woocommerce_thankyou_simplestripe', function ( $order_id ) {
    if ( empty( $_GET['session_id'] ) ) {
        return;
    }

    $settings     = get_option( 'woocommerce_simplestripe_settings', array() );
    $secret_key   = ( isset( $settings['testmode'] ) && 'yes' === $settings['testmode'] )
        ? ( $settings['test_secret_key'] ?? '' )
        : ( $settings['live_secret_key'] ?? '' );
    $autoload     = $settings['autoload_path'] ?? '';

    if ( empty( $secret_key ) || empty( $autoload ) || ! file_exists( $autoload ) ) {
        return;
    }

    require_once $autoload;
    \Stripe\Stripe::setApiKey( $secret_key );

    try {
        $session = \Stripe\Checkout\Session::retrieve( sanitize_text_field( $_GET['session_id'] ) );
        if ( $session && 'paid' === $session->payment_status && ! empty( $session->metadata->order_id ) ) {
            $order = wc_get_order( $session->metadata->order_id );
            if ( $order && ! $order->is_paid() ) {
                $order->payment_complete( $session->payment_intent );
                // If "on-hold" exists (renamed in theme), use it; otherwise leave completed
                $statuses = wc_get_order_statuses();
                if ( isset( $statuses['wc-on-hold'] ) ) {
                    $order->update_status( 'on-hold', 'Stripe payment confirmed.' );
                }
            }
        }
    } catch ( \Exception $e ) {
        // silently ignore
    }
} );

// REST API endpoint for Stripe webhooks
add_action( 'rest_api_init', function () {
    register_rest_route(
        'simplestripe/v1',
        '/webhook',
        array(
            'methods'             => 'POST',
            'permission_callback' => '__return_true',
            'callback'            => function ( WP_REST_Request $request ) {

                $settings   = get_option( 'woocommerce_simplestripe_settings', array() );
                $secret_key = ( isset( $settings['testmode'] ) && 'yes' === $settings['testmode'] )
                    ? ( $settings['test_secret_key'] ?? '' )
                    : ( $settings['live_secret_key'] ?? '' );
                $autoload   = $settings['autoload_path'] ?? '';
                $endpoint_secret = $settings['webhook_secret'] ?? '';

                if ( empty( $secret_key ) || empty( $autoload ) || ! file_exists( $autoload ) ) {
                    return new WP_REST_Response( array( 'error' => 'Stripe SDK or secret key missing.' ), 400 );
                }

                require_once $autoload;
                \Stripe\Stripe::setApiKey( $secret_key );

                $payload    = $request->get_body();
                $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

                try {
                    if ( ! empty( $endpoint_secret ) ) {
                        $event = \Stripe\Webhook::constructEvent(
                            $payload,
                            $sig_header,
                            $endpoint_secret
                        );
                    } else {
                        $event = json_decode( $payload );
                    }
                } catch ( \Exception $e ) {
                    return new WP_REST_Response( array( 'error' => $e->getMessage() ), 400 );
                }

                if ( isset( $event->type ) ) {
                    switch ( $event->type ) {
                        case 'checkout.session.completed':
                        case 'payment_intent.succeeded':
                        case 'charge.succeeded':
                            $object = $event->data->object;
                            $order_id = null;

                            if ( isset( $object->metadata->order_id ) ) {
                                $order_id = (int) $object->metadata->order_id;
                            } elseif ( isset( $object->id ) ) {
                                // fallback: do nothing
                            }

                            if ( $order_id ) {
                                $order = wc_get_order( $order_id );
                                if ( $order && ! $order->is_paid() ) {
                                    $order->payment_complete();
                                    $statuses = wc_get_order_statuses();
                                    if ( isset( $statuses['wc-on-hold'] ) ) {
                                        $order->update_status( 'on-hold', 'Stripe webhook: payment confirmed.' );
                                    }
                                }
                            }
                            break;
                    }
                }

                return new WP_REST_Response( array( 'status' => 'ok' ), 200 );
            },
        )
    );
} );

// Helpers to hide pay/cancel for already paid orders (safe)
add_filter( 'woocommerce_valid_order_statuses_for_payment', function ( $statuses, $order ) {
    if ( $order && $order->is_paid() ) {
        return array();
    }
    return $statuses;
}, 10, 2 );

add_filter( 'woocommerce_my_account_my_orders_actions', function ( $actions, $order ) {
    if ( $order && $order->is_paid() ) {
        unset( $actions['pay'] );
        unset( $actions['cancel'] );
    }
    return $actions;
}, 10, 2 );
