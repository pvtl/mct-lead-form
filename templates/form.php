<?php
/**
 * Shortcode template for 'mct_lead_form'.
 *
 * @package MCT_Lead_Form
 */

?>
<div class="mct-lead-form">
	<h3 class="mct-heading"><?php echo esc_html( $this->attr( 'heading_text' ) ); ?></h3>
	<p class="mct-intro"><?php echo esc_html( $this->attr( 'intro_text' ) ); ?></p>

	<form data-mct-lead-form>
		<div class="form-group">
			<input type="text" class="<?php echo esc_attr( $this->attr( 'input_class' ) ); ?>" name="full_name" placeholder="Full Name" aria-label="Full Name" autocomplete="name" autocapitalize="words">
		</div>

		<div class="form-group">
			<input type="email" class="<?php echo esc_attr( $this->attr( 'input_class' ) ); ?>" name="email"
				placeholder="Email" aria-label="Email" autocomplete="email">
		</div>

		<div class="form-group">
			<input type="text" class="<?php echo esc_attr( $this->attr( 'input_class' ) ); ?>" name="phone_number"
				placeholder="Phone Number" aria-label="Phone Number">
		</div>

		<div class="form-group">
			<select class="<?php echo esc_attr( $this->attr( 'input_class' ) ); ?>" name="state" aria-label="State">
				<option value="" disabled hidden>State</option>
				<option value="ACT">ACT</option>
				<option value="NSW">NSW</option>
				<option value="NT">NT</option>
				<option value="QLD">QLD</option>
				<option value="SA">SA</option>
				<option value="TAS">TAS</option>
				<option value="VIC">VIC</option>
				<option value="WA">WA</option>
			</select>
		</div>

		<button type="submit" class="<?php echo esc_attr( $this->attr( 'button_class' ) ); ?>">
			<?php echo esc_html( $this->attr( 'button_text' ) ); ?>
		</button>
	</form>
</div>
