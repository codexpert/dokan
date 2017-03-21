<?php
/**
 * Dokan email handler class
 *
 * @package Dokan
 */
class Dokan_Email {

    public static function init() {
        static $instance = false;

        if ( !$instance ) {
            $instance = new self;
        }

        return $instance;
    }

    /**
     * Get from name for email.
     *
     * @access public
     * @return string
     */
    function get_from_name() {
        return wp_specialchars_decode( esc_html( get_option( 'woocommerce_email_from_name' ) ), ENT_QUOTES );
    }

    /**
     * Get from email address.
     *
     * @access public
     * @return string
     */
    function get_from_address() {
        return sanitize_email( get_option( 'woocommerce_email_from_address' ) );
    }


    /**
     * Get admin email address
     *
     * @return string
     */
    function admin_email() {
        return apply_filters( 'dokan_email_admin_mail', get_option( 'admin_email' ) );
    }


    /**
     * Get user agent string
     *
     * @return string
     */
    function get_user_agent() {
        return substr( $_SERVER['HTTP_USER_AGENT'], 0, 150 );
    }


    /**
     * Replace currency HTML entities with symbol
     *
     * @param string $amount
     * @return string
     */
    function currency_symbol( $amount ) {
        $price = sprintf( get_woocommerce_price_format(), get_woocommerce_currency_symbol(), $amount );

        return html_entity_decode( $price );
    }


    /**
     * Send email to seller from the seller contact form
     *
     * @param string $seller_email
     * @param string $from_name
     * @param string $from_email
     * @param string $message
     */
    function contact_seller( $seller_email, $from_name, $from_email, $message ) {
        ob_start();
        dokan_get_template_part( 'emails/contact-seller' );
        $body = ob_get_clean();

        $find = array(
            '%from_name%',
            '%from_email%',
            '%user_ip%',
            '%user_agent%',
            '%message%',
            '%site_name%',
            '%site_url%'
        );

        $replace = array(
            $from_name,
            $from_email,
            dokan_get_client_ip(),
            $this->get_user_agent(),
            $message,
            $this->get_from_name(),
            home_url()
        );

        $subject = sprintf( __( '"%s" sent you a message from your "%s" store', 'dokan-lite' ), $from_name, $this->get_from_name() );
        $body = str_replace( $find, $replace, $body);
        $headers = array( "Reply-To: {$from_name}<{$from_email}>" );

        $this->send( $seller_email, $subject, $body, $headers );

        do_action( 'dokan_contact_seller_email_sent', array(
            'to'           => $seller_email,
            'subject'      => $subject,
            'message'      => $body,
            'sender_email' => $from_email,
            'sender_name'  => $from_name,
            'headers'      => $headers,
        ) );
    }


    /**
     * Send seller email notification when a new refund request is made
     *
     * @param WP_User $seller_mail
     * @param int $order_id
     * @param object $refund
     */
    function dokan_refund_seller_mail( $seller_mail, $order_id, $status, $amount, $refund_reason ) {
        ob_start();
        dokan_get_template_part( 'emails/refund-seller-mail' );
        $body = ob_get_clean();
        $find = array(
            '%status%',
            '%order_id%',
            '%amount%',
            '%reason%',
            '%order_page%',
            '%site_name%',
            '%site_url%',
        );

        $replace = array(
            $status,
            $order_id,
            $amount,
            $refund_reason,
            home_url() . '?page=dokan-refund&status=pending',
            $this->get_from_name(),
            home_url(),
        );

        $subject = sprintf( __( '[%s] Refund Request %s', 'dokan-lite' ), $this->get_from_name(), $status );
        $body = str_replace( $find, $replace, $body);

        $this->send( $seller_mail, $subject, $body );
        do_action( 'dokan_after_refund_seller_mail', $seller_mail, $subject, $body );
    }


    /**
     * Send admin email notification when a new refund request is cancle
     *
     * @param string $seller_mail
     * @param int $order_id
     * @param int $refund_id
     */
    function dokan_refund_request( $order_id ) {
        ob_start();
        dokan_get_template_part( 'emails/refund-request' );
        $body = ob_get_clean();
        $find = array(
            '%order_id%',
            '%refund_page%',
            '%site_name%',
            '%site_url%',
        );

        $replace = array(
            $order_id,
            admin_url( 'admin.php?page=dokan-refund&status=pending' ),
            $this->get_from_name(),
            home_url(),
        );

        $subject = sprintf( __( '[%s] New Refund Request', 'dokan-lite' ), $this->get_from_name() );
        $body = str_replace( $find, $replace, $body);
        $this->send( $this->admin_email(), $subject, $body );
        do_action( 'after_dokan_refund_request', $this->admin_email(), $subject, $body );
    }


    /**
     * Prepare body for withdraw email
     *
     * @param string $body
     * @param WP_User $user
     * @param float $amount
     * @param string $method
     * @param string $note
     * @return string
     */
    function prepare_withdraw( $body, $user, $amount, $method, $note = '' ) {
        $find = array(
            '%username%',
            '%amount%',
            '%method%',
            '%profile_url%',
            '%withdraw_page%',
            '%site_name%',
            '%site_url%',
            '%notes%'
        );

        $replace = array(
            $user->user_login,
            $this->currency_symbol( $amount ),
            dokan_withdraw_get_method_title( $method ),
            admin_url( 'user-edit.php?user_id=' . $user->ID ),
            admin_url( 'admin.php?page=dokan-withdraw' ),
            $this->get_from_name(),
            home_url(),
            $note
        );

        $body = str_replace( $find, $replace, $body);

        return $body;
    }


    /**
     * Send admin email notification when a new withdraw request is made
     *
     * @param WP_User $user
     * @param float $amount
     * @param string $method
     */
    function new_withdraw_request( $user, $amount, $method ) {
        ob_start();
        dokan_get_template_part( 'emails/withdraw-new' );
        $body = ob_get_clean();

        $subject = sprintf( __( '[%s] New Withdraw Request', 'dokan-lite' ), $this->get_from_name() );
        $body = $this->prepare_withdraw( $body, $user, $amount, $method );

        $this->send( $this->admin_email(), $subject, $body );
        do_action( 'after_new_withdraw_request', $this->admin_email(), $subject, $body );
    }


    /**
     * Send email to user once a withdraw request is approved
     *
     * @param int $user_id
     * @param float $amount
     * @param string $method
     */
    function withdraw_request_approve( $user_id, $amount, $method ) {
        ob_start();
        dokan_get_template_part( 'emails/withdraw-approve' );
        $body = ob_get_clean();

        $user = get_user_by( 'id', $user_id );
        $subject = sprintf( __( '[%s] Your Withdraw Request has been approved', 'dokan-lite' ), $this->get_from_name() );
        $body = $this->prepare_withdraw( $body, $user, $amount, $method );

        $this->send( $user->user_email, $subject, $body );
        do_action( 'after_withdraw_request_approve', $user->user_email, $subject, $body );
    }


    /**
     * Send email to user once a order has been cancelled
     *
     * @param int $user_id
     * @param float $amount
     * @param string $method
     * @param string $note
     */
    function withdraw_request_cancel( $user_id, $amount, $method, $note = '' ) {
        ob_start();
        dokan_get_template_part( 'emails/withdraw-cancel' );
        $body = ob_get_clean();

        $user = get_user_by( 'id', $user_id );
        $subject = sprintf( __( '[%s] Your Withdraw Request has been cancelled', 'dokan-lite' ), $this->get_from_name() );
        $body = $this->prepare_withdraw( $body, $user, $amount, $method, $note );

        $this->send( $user->user_email, $subject, $body );
        do_action( 'after_withdraw_request_cancel', $user->user_email, $subject, $body );
    }


    /**
     * Send email to admin once a new seller registered
     *
     * @param int $seller_id
     */
    function new_seller_registered_mail( $seller_id ) {

        ob_start();
        dokan_get_template_part( 'emails/new-seller-registered' );
        $body = ob_get_clean();

        $seller = get_user_by( 'id', $seller_id );

        $find = array(
            '%seller_name%',
            '%store_url%',
            '%seller_edit%',
            '%site_name%',
            '%site_url%'
        );

        $replace = array(
            $seller->display_name,
            dokan_get_store_url( $seller_id ),
            admin_url( 'user-edit.php?user_id=' . $seller_id ),
            $this->get_from_name(),
            home_url(),
        );

        $body = str_replace( $find, $replace, $body);
        $subject = sprintf( __( '[%s] New Vendor Registered', 'dokan-lite' ), $this->get_from_name() );

        $this->send( $this->admin_email(), $subject, $body );
        do_action( 'after_new_seller_registered_mail', $this->admin_email(), $subject, $body );
    }




    /**
     * Send email to admin once a product is added
     *
     * @param int $product_id
     * @param string $status
     */
    function new_product_added( $product_id, $status = 'pending' ) {
        $template = 'emails/new-product-pending';

        if ( $status == 'publish' ) {
            $template = 'emails/new-product';
        }
        ob_start();
        dokan_get_template_part($template);
        $body = ob_get_clean();

        $product = wc_get_product( $product_id );
        $seller = get_user_by( 'id', $product->post->post_author );
        $category = wp_get_post_terms($product->id, 'product_cat', array( 'fields' => 'names' ) );
        $category_name = $category ? reset( $category ) : 'N/A';

        $find = array(
            '%title%',
            '%price%',
            '%seller_name%',
            '%seller_url%',
            '%category%',
            '%product_link%',
            '%site_name%',
            '%site_url%'
        );

        $replace = array(
            $product->get_title(),
            $this->currency_symbol( $product->get_price() ),
            $seller->display_name,
            dokan_get_store_url( $seller->ID ),
            $category_name,
            admin_url( 'post.php?action=edit&post=' . $product_id ),
            $this->get_from_name(),
            home_url(),
        );

        $body = str_replace( $find, $replace, $body);
        $subject = sprintf( __( '[%s] New Product Added', 'dokan-lite' ), $this->get_from_name() );

        $this->send( $this->admin_email(), $subject, $body );
        do_action( 'after_new_product_added', $this->admin_email(), $subject, $body );
    }


    /**
     * Send email to seller once a product is published
     *
     * @param WP_Post $post
     * @param WP_User $seller
     */
    function product_published( $post, $seller ) {
        ob_start();
        dokan_get_template_part( 'emails/product-published' );
        $body = ob_get_clean();

        $product = wc_get_product( $post->ID );

        $find = array(
            '%seller_name%',
            '%title%',
            '%product_link%',
            '%product_edit_link%',
            '%site_name%',
            '%site_url%'
        );

        $replace = array(
            $seller->display_name,
            $product->get_title(),
            get_permalink( $post->ID ),
            dokan_edit_product_url( $post->ID ),
            $this->get_from_name(),
            home_url(),
        );

        $body = str_replace( $find, $replace, $body);
        $subject = sprintf( __( '[%s] Your product has been approved!', 'dokan-lite' ), $this->get_from_name() );

        $this->send( $seller->user_email, $subject, $body );
        do_action( 'after_product_published', $seller->user_email, $subject, $body );
    }

    /**
     * Send the email.
     *
     * @access public
     * @param mixed $to
     * @param mixed $subject
     * @param mixed $message
     * @param string $headers
     * @param string $attachments
     * @return void
     */
    function send( $to, $subject, $message, $headers = array() ) {
        add_filter( 'wp_mail_from', array( $this, 'get_from_address' ) );
        add_filter( 'wp_mail_from_name', array( $this, 'get_from_name' ) );

        wp_mail( $to, $subject, $message, $headers );

        remove_filter( 'wp_mail_from', array( $this, 'get_from_address' ) );
        remove_filter( 'wp_mail_from_name', array( $this, 'get_from_name' ) );
    }
}