<?php
/**
 * Cookieless Remember Me
 *
 * @package           CookielessRememberMe
 */

namespace PWCC\CookielessRememberMe;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

const PLUGIN_VERSION = '1.0.0';

/**
 * Bootstrap the plugin.
 */
function bootstrap() {
	/*
	 * This needs to run early before `wp_set_comment_cookies()`.
	 *
	 * The consent form will not be displayed if the core function
	 * is unhooked but we are trying to avoid the cookies from being
	 * set in the first place so our code runs early, redirects the
	 * user via JavaScript and exits.
	 */
	add_action( 'set_comment_cookies', __NAMESPACE__ . '\\set_comment_local_storage', 5, 3 );
	add_action( 'comment_form_submit_field', __NAMESPACE__ . '\\comment_form_submit_field', 10, 2 );
}

/**
 * Set commenter local storage.
 *
 * Store the users data in local storage if they have consented
 * to doing so. After doing so, redirect user to their comment.
 *
 * @param \WP_Comment $comment         Comment object.
 * @param \WP_User    $user            Comment author's user object. The user may not exist.
 * @param bool        $cookies_consent Optional. Comment author's consent to store cookies. Default false.
 */
function set_comment_local_storage( $comment, $user, $cookies_consent = false ) {
	// Remove any existing cookies, they break caching.
	$past = time() - YEAR_IN_SECONDS;
	setcookie( 'comment_author_' . COOKIEHASH, ' ', $past, COOKIEPATH, COOKIE_DOMAIN );
	setcookie( 'comment_author_email_' . COOKIEHASH, ' ', $past, COOKIEPATH, COOKIE_DOMAIN );
	setcookie( 'comment_author_url_' . COOKIEHASH, ' ', $past, COOKIEPATH, COOKIE_DOMAIN );

	if ( $user->exists() ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- front end form for logged out users.
	$location = empty( $_POST['redirect_to'] ) ? get_comment_link( $comment ) : sanitize_url( wp_unslash( $_POST['redirect_to'] ) . '#comment-' . $comment->comment_ID );

	if ( 'unapproved' === wp_get_comment_status( $comment ) && ! empty( $comment->comment_author_email ) ) {
		$location = add_query_arg(
			array(
				'unapproved'      => $comment->comment_ID,
				'moderation-hash' => wp_hash( $comment->comment_date_gmt ),
			),
			$location
		);
	}

	if ( ! $cookies_consent ) {
		$local_storage = array();
	} else {
		$local_storage = array(
			'author' => $comment->comment_author,
			'email'  => $comment->comment_author_email,
			'url'    => $comment->comment_author_url,
			/** Documented in wp-includes/comment.php */
			'expiry' => time() + apply_filters( 'comment_cookie_lifetime', YEAR_IN_SECONDS ), //phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
		);
	}

	$local_storage_name = 'comment_author_prefill_' . COOKIEHASH;

	?>
	<html>
		<head>
			<title>Redirecting</title>
			<script>
				if ( <?php echo wp_json_encode( (bool) $cookies_consent ); ?> ) {
					localStorage.setItem( <?php echo wp_json_encode( $local_storage_name ); ?>, JSON.stringify( <?php echo wp_json_encode( $local_storage ); ?> ) );
				} else {
					// Remove existing item, user may have changed their mind.
					localStorage.removeItem( <?php echo wp_json_encode( $local_storage_name ); ?> );
				}
				location.replace( <?php echo wp_json_encode( $location ); ?> );
			</script>
		</head>
	</html>
	<?php
	exit;
}

/**
 * Append the JavaScript for local storage to the comment form.
 *
 * Adds the JavaScript required for populating a comment form's author
 * data to the end of a form. The submit field is used because that's the
 * closest hook I could find that contains the arguments used to generate
 * the comment form.
 *
 * @param string $submit_field The submit field.
 * @param array  $args         The arguments passed to comment_form().
 * @return string Submit field with associated JavaScript.
 */
function comment_form_submit_field( $submit_field, $args ) {
	$script = <<<'HTML'
		<script>
			(() => {
				let commentForm = document.querySelector( %1$s ),
					commenter,
					now = Date.now() / 1000; // Stored in seconds.
				if ( ! commentForm ) {
					return;
				}

				try {
					commenter = JSON.parse( localStorage.getItem( %2$s ) );
				} catch (e) {
					return;
				}
				if ( ! commenter || ! commenter.expiry ) {
					return;
				}

				if ( now > commenter.expiry ) {
					// Content has expired.
					localStorage.removeItem( %2$s );
					return;
				}

				if ( ! commenter.email ) {
					// Double check for prior consent (indicated by a stored email ).
					return;
				}

				commentForm.querySelector( '[name="wp-comment-cookies-consent"]' ).setAttribute( 'checked', 'checked' );
				commentForm.querySelector( '[name="author"]' ).setAttribute( 'value', commenter.author );
				commentForm.querySelector( '[name="email"]' ).setAttribute( 'value', commenter.email );
				commentForm.querySelector( '[name="url"]' ).setAttribute( 'value', commenter.url );
			})();
		</script>
	HTML;

	$script = sprintf(
		$script,
		wp_json_encode( "#{$args['id_form']}" ),
		wp_json_encode( 'comment_author_prefill_' . COOKIEHASH )
	);

	return $submit_field . $script;
}
