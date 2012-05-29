<div class="wrap">
	<h2>Google Calender Options</h2>
	
	<?php
		if($_POST['gc_saved'] == 'Y'){
			echo "<div class='updated'><p>saved</p></div>";
		}
	?>
	
	<form action="" method="post">
		<input type="hidden" name="gc_saved" value="Y" />
		<table class="form-table">
			<tr>
				<td>Application Name</td>
				<td cospan="2"><input size="60" type="text" name="gc_app_name" value="<?php echo $gc['app_name']; ?>" /></td>
			</tr>
			<tr>
				<td>Client Id</td>
				<td cospan="2"><input size="60" type="text" name="gc_client_id" value="<?php echo $gc['client_id']; ?>" /></td>
			</tr>
			<tr>
				<td>Client Secret </td>
				<td cospan="2"><input size="60" type="text" name="gc_client_secret" value="<?php echo $gc['client_secret']; ?>" /></td>
			</tr>
			
			<tr> 
				<td>Redirect Urls</td>
				<td><input size="60" type="text" value="<?php echo get_option('siteurl') . '/wp-admin/google-calender?redirect=yes'; ?>" readonly /> </td>
			</tr>
			
			<tr>
				<td>Time Zone </td>
				<td>
					<select name="gc_timezone">
						<option value="">Select a Timezone</option>
						<?php echo self::get_timezone_options($timezone); ?>
					</select>
				</td>
			</tr>
			
			<tr>
				<td>
					get or set everything
				</td>
				<td><a target="_blank" href="https://code.google.com/apis/console?api=calendar">Visit Google calender console</a></td>
			</tr>
			
			<tr>
				<td><input type="submit" value="save" class="button-primary"  /></td>
			</tr>
		</table>
	</form>
</div>

<hr/>
<div class="wrap">
	<h2>Calender Status</h2>
	<?php
		if(self::calender_enabled()){
			echo "<div>Activated</div>";
			?>
			
			<form  action='' method='post'>
				<h3>Want to deactivate?</h3>
				<p>
					<input type='checkbox' name='deactivate_the_Claneder' value="Y" /> I agree
					<input type="submit" value="Deactivate" />
				</p>
			</form>
			
			<?php
		}
		else{
			$gcalender = self::get_calender();
			$url = $gcalender->getAuthSubUrl();			
			echo "<a href='$url'>Activate Google Calendar</a>";
			$_SESSION['gc_redirect_url'] = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		}
	?>
</div>
