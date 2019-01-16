<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// require_once(APPPATH.'controllers/Fall_detection.php');

class Patient extends CI_controller {

	public function __construct()
    {
        parent::__construct();
        // date_default_timezone_set('Asia/Kolkata');
    }

	/**
	 * Patient Page for this controller.
	 *
	 *
	 */
	public function index()
	{
		$this->load->view('patient');
	}

	public function import_csv()
	{
		$sensor_data_a1 = NULL;
		$sensor_data_g = NULL;
		$sensor_data_a2 = NULL;

		$file_name = basename($_FILES["sensor_data"]["name"]);
		$sensor_data_file = APPPATH.'temp/'. $file_name;

		$ext_header = pathinfo($file_name, PATHINFO_EXTENSION);
		if ($ext_header != "csv" && $ext_header != "txt") {
			// Upload correct file format
			echo "File not csv";
			return;
		} else {
			if (move_uploaded_file($_FILES['sensor_data']['tmp_name'], $sensor_data_file)) {
				$file = fopen($sensor_data_file, "r") or die("Problem open file");
				$file_size = filesize($sensor_data_file);

				if (!$file_size) {
					// File empty
					echo "File seems to be empty";
					return;
				}
				$csv_content = fread($file, $file_size);
				fclose($file);

				$line_separator = "\n";
				$csv_line_content = array();
				$row = 1;
				$count = 0;
				foreach (explode($line_separator, $csv_content) as $line) {
					$line = trim($line, " \t");
					$line = str_replace("\r", "", $line);
					$csv_line_content = str_getcsv($line, ",", "\""); // (line, field separator, line)

					if (!empty($csv_line_content) && count($csv_line_content) > 1) { // line should not be empty; else skip to next line
						if ($row == 1) {
							$row++;
							continue;
						}

						// Accleration - 1
						$sensor_data_a1[$count]['x'] = $this->process_signal(1, $csv_line_content[0]);
						$sensor_data_a1[$count]['y'] = $this->process_signal(1, $csv_line_content[1]);
						$sensor_data_a1[$count]['z'] = $this->process_signal(1, $csv_line_content[2]);

						// Gyro
						$sensor_data_g[$count]['x'] = $this->process_signal(2, $csv_line_content[3]);
						$sensor_data_g[$count]['y'] = $this->process_signal(2, $csv_line_content[4]);
						$sensor_data_g[$count]['z'] = $this->process_signal(2, $csv_line_content[5]);

						// Accleration - 2
						$sensor_data_a2[$count]['x'] = $this->process_signal(3, $csv_line_content[6]);
						$sensor_data_a2[$count]['y'] = $this->process_signal(3, $csv_line_content[7]);
						$sensor_data_a2[$count]['z'] = $this->process_signal(3, $csv_line_content[8]);
						$count++;
					}
				}
			} else {
				// Filesystem permission issue
				echo "Filesystem permission issue!";
				return;
			}
		}
$this->overall_acceleration($sensor_data_a1, $sensor_data_g, $sensor_data_a2);
		// if($sensor_data_a1 =! NULL || $sensor_data_g =! NULL || $sensor_data_a2 =! NULL) {
			
		// 	$this->overall_acceleration($sensor_data_a1, $sensor_data_g, $sensor_data_a2);
		// } else {
		// 	echo "123";
		// }
	}

	public function process_signal($sensor_type, $sensor_value) {
		if($sensor_type == 1) {
			// Accleration - 1
			// Acceleration [g]: [(2*Range)/(2^Resolution)]*AD

			// ADXL345:
			// Resolution: 13 bits
			// Range: +-16g

			$range_acc1 = (int)32; // +- 16G
			$resolution_acc1 = (int)13; // 13 bits
			return (((2*$range_acc1)/(pow(2,$resolution_acc1)))*$sensor_value);
		} else if($sensor_type == 2) {
			// Gyroscope
			// Angular velocity [°/s]: [(2*Range)/(2^Resolution)]*RD

			// ITG3200
			// Resolution: 16 bits
			// Range: +-2000°/s

			$range_gyro = (int)4000; // +- 2000
			$resolution_gyro = (int)16; // 16 bits
			return (((2*$range_gyro)/(pow(2,$resolution_gyro)))*$sensor_value);
		} else if($sensor_type == 3) {
			// Accleration - 2
			// Acceleration [g]: [(2*Range)/(2^Resolution)]*AD

			// MMA8451Q:
			// Resolution: 14 bits
			// Range: +-8g

			$range_acc2 = (int)16; // +- 8G
			$resolution_acc2 = (int)14; // 14 bits
			return (((2*$range_acc2)/(pow(2,$resolution_acc2)))*$sensor_value);
		} else {
			return 0;
		}
	}

	public function overall_acceleration($sensor_data_a1, $sensor_data_g, $sensor_data_a2)
	{
		// Sensor frequency sample is 200 HZ, i.e, 5ms interval between sensor data
		$sensor_frequency = 0.005;

		// Overall accln and angular velocity calculations
		$acceleration = NULL;
		$angular_velocity = NULL;

		$data_count = count($sensor_data_a1);
		for ($i=0; $i < $data_count; $i++) { 
			$a1 = sqrt(pow(2,$sensor_data_a1[$i][x]) + pow(2,$sensor_data_a1[$i][y]) + pow(2,$sensor_data_a1[$i][z]));
			$a2 = sqrt(pow(2,$sensor_data_a2[$i][x]) + pow(2,$sensor_data_a2[$i][y]) + pow(2,$sensor_data_a2[$i][z]));
			$angular_velocity[] = sqrt(pow(2,$sensor_data_g[$i][x]) + pow(2,$sensor_data_g[$i][y]) + pow(2,$sensor_data_g[$i][z]));
			$acceleration[] = ($a1 + $a2)/2; // Taking avg of the 2 sensors so as to improve accuracy of acceleration
		}

		echo "<pre>";
		var_dump($acceleration);

		return;

	}

	public function thresholds($sensor_data_a1, $sensor_data_g, $sensor_data_a2)
	{
		if($sensor_type == 1) {
			// Accelerometer - 1
			return array(
							'x' => 0,
							'y' => 0,
							'z' => 0,
						);
		} else if($sensor_type == 2) {
			// Accelerometer - 2
			return array(
							'x' => 0,
							'y' => 0,
							'z' => 0,
						);
		} else if($sensor_type == 3) {
			// Gyroscope
			return array(
							'x' => 0,
							'y' => 0,
							'z' => 0,
						);
		} else {
			return NULL;
		}
	}
}