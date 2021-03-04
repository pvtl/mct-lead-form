<?php
/**
 * Shortcode template for 'mct_lead_form'.
 *
 * @package MCT_Lead_Form
 */

?>
<div class="mct-lead-form" data-mct-lead-form="<?php echo esc_url( get_rest_url( null, 'mct' ) ); ?>"
	data-redirect="<?php echo esc_url( MCT_LEAD_FORM_REDIRECT ); ?>">
	<h3 class="mct-heading"><?php echo esc_html( $this->attr( 'heading_text' ) ); ?></h3>
	<p class="mct-intro"><?php echo esc_html( $this->attr( 'intro_text' ) ); ?></p>

	<div class="mct-success-message" data-mct-message="success"></div>
	<div class="mct-error-message" data-mct-message="error"></div>

	<form data-mct-stage="1" data-method="POST" data-endpoint="leads"
		data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">

		<div class="form-group">
			<input type="text" class="<?php echo esc_attr( $this->attr( 'input_class' ) ); ?>" name="full_name" placeholder="Full Name *" aria-label="Full Name" autocomplete="name" autocapitalize="words" required>
			<div class="invalid-feedback"></div>
		</div>

		<div class="form-group">
			<input type="email" class="<?php echo esc_attr( $this->attr( 'input_class' ) ); ?>" name="email"
				placeholder="Email *" aria-label="Email" autocomplete="email" required>
			<div class="invalid-feedback"></div>
		</div>

		<div class="form-group">
			<input type="text" class="<?php echo esc_attr( $this->attr( 'input_class' ) ); ?>" name="phone_number"
				placeholder="Phone *" aria-label="Phone Number" required>
			<div class="invalid-feedback"></div>
		</div>

		<div class="form-group">
			<select class="<?php echo esc_attr( $this->attr( 'input_class' ) ); ?>" name="state" aria-label="State"
				required>
				<option value="" selected hidden>State *</option>
				<option value="ACT">ACT</option>
				<option value="NSW">NSW</option>
				<option value="NT">NT</option>
				<option value="QLD">QLD</option>
				<option value="SA">SA</option>
				<option value="TAS">TAS</option>
				<option value="VIC">VIC</option>
				<option value="WA">WA</option>
			</select>
			<div class="invalid-feedback"></div>
		</div>

		<button type="submit" class="<?php echo esc_attr( $this->attr( 'button_class' ) ); ?>">
			<?php echo esc_html( $this->attr( 'button_text' ) ); ?>
		</button>
	</form>

	<form data-mct-stage="2" data-method="PATCH" data-endpoint="leads/{id}"
		data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>" style="display: none;">

		<div class="form-group">
			<input type="text" class="<?php echo esc_attr( $this->attr( 'input_class' ) ); ?>" name="vehicle_rego"
				placeholder="Vehicle rego" aria-label="Vehicle rego" autocomplete="off">
			<div class="invalid-feedback"></div>
		</div>

		<div class="form-group">
			<select class="<?php echo esc_attr( $this->attr( 'input_class' ) ); ?>" name="vehicle_rego_state" aria-label="Vehicle registered in state">
				<option value="" selected hidden>Vehicle registered in state</option>
				<option value="ACT">ACT</option>
				<option value="NSW">NSW</option>
				<option value="NT">NT</option>
				<option value="QLD">QLD</option>
				<option value="SA">SA</option>
				<option value="TAS">TAS</option>
				<option value="VIC">VIC</option>
				<option value="WA">WA</option>
			</select>
			<div class="invalid-feedback"></div>
		</div>

		<div class="form-group">
			<input type="text" class="<?php echo esc_attr( $this->attr( 'input_class' ) ); ?>" name="vehicle_make"
				placeholder="Vehicle make *" aria-label="Vehicle make" autocomplete="off" required>
			<div class="invalid-feedback"></div>
		</div>

		<div class="form-group">
			<input type="text" class="<?php echo esc_attr( $this->attr( 'input_class' ) ); ?>" name="vehicle_model"
				placeholder="Vehicle model *" aria-label="Vehicle model" autocomplete="off" required>
			<div class="invalid-feedback"></div>
		</div>

		<div class="form-group">
			<input type="number" min="1900" max="<?php echo esc_attr( ( (int) current_time( 'Y' ) ) + 1 ); ?>" step="1" class="<?php echo esc_attr( $this->attr( 'input_class' ) ); ?>" name="vehicle_year"
				placeholder="Vehicle year *" aria-label="Vehicle year" autocomplete="off" required>
			<div class="invalid-feedback"></div>
		</div>

		<div class="form-group">
			<input type="number" min="0" step="1" class="<?php echo esc_attr( $this->attr( 'input_class' ) ); ?>" name="vehicle_odometer"
				placeholder="Vehicle kilometers *" aria-label="Vehicle kilometers" autocomplete="off" required>
			<div class="invalid-feedback"></div>
		</div>

		<div class="form-group">
			<select class="<?php echo esc_attr( $this->attr( 'input_class' ) ); ?>" name="vehicle_transmission" aria-label="Vehicle transmission" required>
				<option value="" selected hidden>Vehicle transmission *</option>
				<option value="Automatic">Automatic</option>
				<option value="Manual">Manual</option>
			</select>
			<div class="invalid-feedback"></div>
		</div>

		<div class="form-footer">
			<button type="button" data-prev class="<?php echo esc_attr( $this->attr( 'button_class' ) ); ?> btn-prev">
				Previous
			</button>

			<button type="submit" class="<?php echo esc_attr( $this->attr( 'button_class' ) ); ?>">
				Submit
			</button>
		</div>
	</form>
</div>
