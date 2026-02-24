<?php
/**
 * Theme Functions Additions
 *
 * These code snippets must be added to the community-foundation theme's
 * functions.php file. They are tracked here because the theme is not in
 * this repository.
 *
 * File to edit: wp-content/themes/community-foundation/functions.php
 *
 * Deployment status:
 *   Staging:    Applied 2026-02-24
 *   Production: Not yet applied
 */


/**
 * FIX: Mobile fund designation race condition (2026-02-24)
 *
 * Problem: On mobile, the Classy donation form's "I'd like to support" field
 * defaulted to "General Fund Project" instead of the specific fund page the
 * user was visiting.
 *
 * Root cause: The Classy SDK loads async in <head> and could initialize before
 * the inline designation-setting script in fund-form.php (in <body>) had a
 * chance to run, causing Classy to read the URL before ?designation= was set.
 *
 * Fix: Output the designation-setting script at wp_head priority 1 so it runs
 * before the browser even encounters the Classy <script async> tag.
 *
 * See: docs/theme-fund-form-embed.md for full details.
 */
add_action( 'wp_head', 'fcg_set_fund_designation_early', 1 );
function fcg_set_fund_designation_early() {
	if ( ! is_singular( 'funds' ) ) return;
	$designation_id = get_post_meta( get_the_ID(), '_gofundme_designation_id', true );
	if ( ! $designation_id ) return;
	?>
	<script>
	(function() {
		var url = new URL(window.location.href);
		var id = '<?php echo esc_js( $designation_id ); ?>';
		if (url.searchParams.get('designation') !== id) {
			url.searchParams.set('designation', id);
			window.history.replaceState({}, '', url.toString());
		}
	})();
	</script>
	<?php
}
