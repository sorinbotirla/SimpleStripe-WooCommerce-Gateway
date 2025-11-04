
    <h1>SimpleStripe WooCommerce Gateway</h1>
    <p>
      <strong>SimpleStripe WooCommerce Gateway</strong> is a lightweight plugin
      that adds a Stripe Checkout–based payment method to WooCommerce. It is
      designed to be clean, self-contained, and easy to customize for personal
      or commercial projects.
    </p>

    <h2>Features</h2>
    <ul>
      <li>Redirects customers to Stripe Checkout using the Stripe PHP SDK.</li>
      <li>Supports both classic and WooCommerce Blocks checkout flows.</li>
      <li>Provides test and live secret keys and a webhook signing secret field.</li>
      <li>Updates WooCommerce order status after successful payment.</li>
      <li>Includes webhook endpoint validation for Stripe events.</li>
      <li>Does not send duplicate emails or reprocess payments on refresh.</li>
    </ul>

    <h2>Requirements</h2>
    <ul>
      <li>WordPress 5.0+</li>
      <li>WooCommerce 5.0+</li>
      <li>PHP 7.4 or later</li>
      <li>Stripe account (for API keys and webhooks)</li>
    </ul>

    <h2>Folder Structure</h2>
    <pre>
simplestripe-woocommerce-gateway/
├── simplestripe-woocommerce-gateway.php
└── assets/
    └── js/
        └── blocks.js
    </pre>

    <h2>Installation</h2>
    <ol>
      <li>Download or clone this repository.</li>
      <li>Copy the <code>simplestripe-woocommerce-gateway</code> folder into <code>wp-content/plugins/</code>.</li>
      <li>Activate the plugin from <strong>WordPress → Plugins</strong>.</li>
    </ol>

    <h2>Installing Stripe PHP SDK</h2>
    <p>
      The plugin relies on the official <strong>Stripe PHP SDK</strong>. This is
      not bundled to keep the plugin light. You must install or download the SDK
      manually before using the plugin.
    </p>
    <p>You have three options:</p>
    <ol>
      <li>
        <strong>Use Composer (recommended):</strong><br />
        Run this in your site’s root or any writable directory:
        <pre>composer require stripe/stripe-php</pre>
        Then set the plugin’s <strong>Path to autoload.php</strong> to the full
        path of the generated <code>vendor/autoload.php</code> file.
      </li>
      <li>
        <strong>Download manually:</strong><br />
        Visit <a href="https://github.com/stripe/stripe-php" target="_blank">Stripe PHP on GitHub</a>,
        download the release ZIP, and extract it somewhere accessible.
      </li>
      <li>
        <strong>Use a hosted vendor folder:</strong><br />
        You can copy an existing <code>vendor</code> directory from another
        plugin or project that already includes <code>stripe/stripe-php</code>.
      </li>
    </ol>

    <p>
      Example full path you might enter in settings:
      <code>/home/youruser/public_html/vendor/autoload.php</code>
    </p>

    <h2>Configuration</h2>
    <ol>
      <li>Go to <strong>WooCommerce → Settings → Payments</strong>.</li>
      <li>Enable <strong>Pay by card (Stripe)</strong>.</li>
      <li>Click the method name to edit settings.</li>
      <li>
        Fill in:
        <ul>
          <li><strong>Test secret key</strong> (sk_test_...)</li>
          <li><strong>Live secret key</strong> (sk_live_...)</li>
          <li><strong>Webhook signing secret</strong> (whsec_...)</li>
          <li><strong>Path to autoload.php</strong></li>
        </ul>
      </li>
    </ol>

    <h2>Webhook Endpoint</h2>
    <p>Once configured, add this webhook to your Stripe Dashboard:</p>
    <p><code>https://your-site.tld/wp-json/simplestripe/v1/webhook</code></p>
    <p>Supported events:</p>
    <ul>
      <li>checkout.session.completed</li>
      <li>payment_intent.succeeded</li>
      <li>charge.succeeded</li>
    </ul>

    <h2>Order Status Handling</h2>
    <p>
      The plugin safely checks if the target status exists before applying it.
      You can customize this behavior in your theme or an additional plugin.
    </p>
    <pre>
add_action( 'woocommerce_payment_complete', function( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }

    // Only for SimpleStripe
    if ( $order->get_payment_method() !== 'simplestripe' ) {
        return;
    }

    // Safely set to on-hold if available
    if ( array_key_exists( 'wc-on-hold', wc_get_order_statuses() ) ) {
        $order->update_status( 'on-hold', 'Stripe payment confirmed.' );
    }
});
    </pre>

    <h2>WooCommerce Blocks</h2>
    <p>
      The plugin automatically registers its payment method with WooCommerce
      Blocks. The file <code>assets/js/blocks.js</code> is loaded only on the
      checkout page.
    </p>

    <h2>Notes</h2>
    <ul>
      <li>Does not include the Stripe PHP SDK — install it manually or with Composer.</li>
      <li>Webhook verification requires a valid signing secret from Stripe.</li>
      <li>If you fork this repository, update the REST route and text domain.</li>
    </ul>

    <h2>License</h2>
    <p>
      Open source under MIT or GPL-2.0+. You can choose the license when
      publishing on GitHub.
    </p>
