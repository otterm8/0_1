<?php
define('QUADODO_IN_SYSTEM', true);
require_once './includes/header.php';
require_once './includes/redirectToLogin.php';
$qls->Security->check_auth_page('user.php');
?>

<?php require 'includes/header_start.php'; ?>
<?php require 'includes/header_end.php'; ?>


<!-- Page-Title -->
<div class="row">
    <div class="col-sm-12">
        <h4 class="page-title">Account Settings</h4>
    </div>
</div>

<div class="row">
	<div class="col-xs-12 col-sm-6 col-md-5 col-lg-5 col-xl-5">
		<div class="card-box table-responsive">
			<h4 class="m-t-0 header-title"><b>Timezone</b></h4>
			<?php
				$regions = array(
					'Africa' => DateTimeZone::AFRICA,
					'America' => DateTimeZone::AMERICA,
					'Antarctica' => DateTimeZone::ANTARCTICA,
					'Aisa' => DateTimeZone::ASIA,
					'Atlantic' => DateTimeZone::ATLANTIC,
					'Europe' => DateTimeZone::EUROPE,
					'Indian' => DateTimeZone::INDIAN,
					'Pacific' => DateTimeZone::PACIFIC
				);

				$timezones = array();
				foreach ($regions as $name => $mask) {
					$zones = DateTimeZone::listIdentifiers($mask);
					foreach($zones as $timezone) {
						// Lets sample the time there right now
						$time = new DateTime(NULL, new DateTimeZone($timezone));

						// Convert to 12 hour clock
						$ampm = $time->format('H') > 12 ? ' ('.$time->format('g:i a').')' : '';

						// Remove region name and add a sample time
						$timezones[$name][$timezone] = substr($timezone, strlen($name) + 1).' - '.$time->format('H:i') . $ampm;
					}
				}
				
				echo '<select class="form-control" id="selectTimezone">';
				foreach($timezones as $region => $list) {
					echo '<optgroup label="'.$region.'">'."\n";
					foreach($list as $timezone => $name) {
						$selected = $timezone == $qls->user_info['timezone'] ? 'selected' : '';
						echo '<option value="'.$timezone.'" name="'.$timezone.'" '.$selected.'>'.$name.'</option>'."\n";
					}
					print '<optgroup>'."\n";
				}
				echo '</select>';
			?>
		</div>
	</div>
	
	<div class="col-xs-12 col-sm-6 col-md-5 col-lg-5 col-xl-5">
		<div class="card-box">
			<h4 class="m-t-0 header-title"><b>Default Scan Method</b></h4>
			<div class="radio">
				<input class="radioScanMethod" type="radio" name="radio" id="radio1" value="manual"<?php echo $qls->user_info['scanMethod'] ? '' : ' checked'; ?>>
				<label for="radio1">Manual</label>
			</div>
			<div class="radio">
				<input class="radioScanMethod" type="radio" name="radio" id="radio2" value="barcode"<?php echo $qls->user_info['scanMethod'] ? ' checked' : ''; ?>>
				<label for="radio2">Barcode</label>
			</div>
		</div>
	</div>
</div> <!-- end row -->

<?php require 'includes/footer_start.php' ?>

<script src="assets/pages/jquery.settings.js"></script>

<?php require 'includes/footer_end.php' ?>
