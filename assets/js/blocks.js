( function( wp ) {
    const { registerPaymentMethod } = wc.wcBlocksRegistry || wp.wc.wcBlocksRegistry || window.wc.wcBlocksRegistry;

    if ( typeof registerPaymentMethod !== 'function' ) {
        return;
    }

    registerPaymentMethod( {
        name: 'simplestripe',
        label: 'Pay by Card (Stripe)',
        content: wp.element.createElement( 'p', null, 'Pay securely through Stripe Checkout.' ),
        edit: wp.element.createElement( 'p', null, 'Pay securely through Stripe Checkout.' ),
        canMakePayment: () => true,
        ariaLabel: 'Pay by Card (Stripe)',
        supports: {
            features: [ 'products' ],
        },
    } );
} )( window.wp || {} );
