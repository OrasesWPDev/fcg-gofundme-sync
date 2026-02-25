<?php
/**
 * Fund Donation Form Template (Classy Embed)
 *
 * Renders the Classy embedded donation form with designation pre-selection.
 * Requires master campaign and component IDs from plugin settings.
 *
 * On single fund pages: Embed renders immediately. The ?designation= URL
 * parameter is set early via wp_head priority 1 in functions.php before
 * the Classy SDK async script is encountered, eliminating the mobile race
 * condition where the SDK initialized before the parameter was set.
 *
 * On archive pages (modals): Embed is lazy-loaded when modal opens.
 *
 * File to edit: wp-content/themes/community-foundation/fund-form.php
 *
 * Deployment status:
 *   Staging:    Pending — apply after plugin deployment
 *   Production: Not yet applied
 *
 * @package Community_Foundation
 * @since 2.3.0
 */

// Get required configuration values
$designation_id = get_post_meta(get_the_ID(), '_gofundme_designation_id', true);
$master_campaign_id = get_option('fcg_gofundme_master_campaign_id');
$master_component_id = get_option('fcg_gofundme_master_component_id');
$is_single_fund = is_singular('funds');
?>

<h4>Make a Donation</h4>

<?php if ($designation_id && $master_campaign_id && $master_component_id): ?>

	<!-- Classy Embedded Donation Form -->
	<!-- Note: ?designation= is set early via wp_head priority 1 in functions.php -->
	<div id="<?php echo esc_attr($master_component_id); ?>"
	     classy="<?php echo esc_attr($master_campaign_id); ?>"></div>

<?php else: ?>
	<!-- Fallback when embed not configured -->
	<div class="donate-form-fallback">
		<p class="text-muted">
			Online donations for this fund are coming soon.
			Please <a href="/contact/">contact us</a> to make a donation.
		</p>
	</div>
<?php endif; ?>
