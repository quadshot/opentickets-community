<?php get_header(); ?>
<div class="form-wrapper">
	<h3>Additional Information is Required</h3>
	<p>
		In order to allow you to view your ticket,
		we must first verify who you are,
		and that you should be able to view this ticket.
		To do that, we need to obtain some information from you,
		that we will use to verify who you are.
		If this information matches our record,
		then your ticket will be available to you.
	</p>
	<form action="" method="post">
		<label>Email</label>
		<input type="email" name="email" value="" class="widefat" />
		<div class="helper">
			This should be the email you used during the purchase of your ticket, as your billing email address.
		</div>

		<input type="hidden" name="verification_form" value="1" />
		<input type="submit" value="Submit" />
	</form>
</div>
<?php get_footer();
