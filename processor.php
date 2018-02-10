<?php
session_start();
//var_dump($_FILES);
//includes
if(isset($_FILES)) { //Check to see if a file is uploaded
    try {
        if (($log = fopen("log.txt", "w")) === false) { //open a log file
            //if unable to open throw exception
            throw new RuntimeException("Log File Did Not Open.");
        }
        $today = new DateTime('now'); //create a date for now
        fwrite($log, $today->format("Y-m-d H:i:s") . PHP_EOL); //post the date to the log
        fwrite($log, "--------------------------------------------------------------------------------" . PHP_EOL); //post to log
        $name = $_FILES['file']['name']; //get file name
        fwrite($log, "FileName: $name" . PHP_EOL); //write to log
        $type = $_FILES["file"]["type"];//get file type
        fwrite($log, "FileType: $type" . PHP_EOL); //write to log
        $tmp_name = $_FILES['file']['tmp_name']; //get file temp name
        fwrite($log, "File TempName: $tmp_name" . PHP_EOL); //write to log
        $tempArr = explode(".", $_FILES['file']['name']); //set file name into an array
        $extension = end($tempArr); //get file extension
        fwrite($log, "Extension: $extension" . PHP_EOL); //write to log
        //If any errors throw an exception
        if (!isset($_FILES['file']['error']) || is_array($_FILES['file']['error'])) {
            fwrite($log, "Invalid Parameters - No File Uploaded." . PHP_EOL);
            throw new RuntimeException("Invalid Parameters - No File Uploaded.");
        }
        //switch statement to determine action in relationship to reported error
        switch ($_FILES['file']['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                fwrite($log, "No File Sent." . PHP_EOL);
                throw new RuntimeException("No File Sent.");
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                fwrite($log, "Exceeded Filesize Limit." . PHP_EOL);
                throw new RuntimeException("Exceeded Filesize Limit.");
            default:
                fwrite($log, "Unknown Errors." . PHP_EOL);
                throw new RuntimeException("Unknown Errors.");
        }
        //check file size
        if ($_FILES['file']['size'] > 2000000) {
            fwrite($log, "Exceeded Filesize Limit." . PHP_EOL);
            throw new RuntimeException('Exceeded Filesize Limit.');
        }
        //define accepted extensions and types
        $goodExts = array("csv");
        $goodTypes = array("text/csv", "application/vnd.ms-excel", "application/csv");
        //test to ensure that uploaded file extension and type are acceptable - if not throw exception
        if (in_array($extension, $goodExts) === false || in_array($type, $goodTypes) === false) {
            fwrite($log, "This page only accepts .csv files, please upload the correct format." . PHP_EOL);
            throw new Exception("This page only accepts .csv files, please upload the correct format.");
        }
        //move the file from temp location to the server - if fail throw exception
        $directory = "/var/www/html/DowntownEnterprises/Files";
        if (move_uploaded_file($tmp_name, "$directory/$name")) {
            fwrite($log, "File Successfully Uploaded." . PHP_EOL);
        } else {
            fwrite($log, "Unable to Move File to /Files." . PHP_EOL);
            throw new RuntimeException("Unable to Move File to /Files.");
        }
        //rename the file using todays date and time
        $month = $today->format("m");
        $day = $today->format('d');
        $year = $today->format('y');
        $time = $today->format('H-i-s');
        $newName = "$directory/DowntownEnterprises-$month-$day-$year-$time.$extension";
        if ((rename("$directory/$name", $newName))) {
            fwrite($log, "File Renamed to: $newName" . PHP_EOL);
        } else {
            fwrite($log, "Unable to Rename File: $name" . PHP_EOL);
            throw new RuntimeException("Unable to Rename File: $name");
        }
        if(!$handle = fopen($newName, "r")){
            throw new Exception('Unable to open writing stream');
        }
        $headers = fgets($handle);
        //var_dump($headers);
        $fileData = array();
        //read the data in line by line
        while (!feof($handle)) {
            $fileData[] = fgetcsv($handle);
        }
        //close file reading stream
        fclose($handle);
        //var_dump($fileData);


        $data = $sum = array();
        foreach($fileData as $key => $arr){
            if($arr != false) {
                //pay-rate is not empty
                if($arr[12] != '') {
                    //employee-num is not empty
                    if($arr[17] != ''){
                        $data[$arr[17]][$arr[5]][$arr[11]][] = array('EE Number' => $arr[17], 'time' => $arr[11], 'hours' => (float) $arr[25], 'amount' => (float) $arr[26], 'rate' => (float) $arr[12], 'dept' => ucfirst(strtolower($arr[15])));
                        $sum[] = (float) $arr[26];
                        $totalHours[] = (float) $arr[25];
                    }
                }

            }
        }
        //var_dump("DATA", $data);

        $output = array();
        foreach($data as $ee => $array){
            foreach($array as $name => $arr){
                foreach($arr as $key => $a){
                    $code = '';
                    switch($key){
                        case 'ADJ':
                            $code = '';
                            break;
                        case 'REG':
                            $code = '01'; //Regular
                            break;
                        case 'OT':
                            $code = '02'; //OT
                            break;
                        case 'PDIEM':
                            $code = '20'; //PerDiem
                            break;
                        case 'PRO':
                            $code = '07'; //Salary
                            break;
                        case 'PTO':
                            $code = '03'; //Vacation
                            break;
                        case 'TRAVE':
                            $code = '25'; //Travel
                            break;
                        case 'HOL':
                            $code = '05'; //Holiday
                            break;
                    }

                    foreach($a as $k => $value){
                        $hours = $value['hours'];
                        $amount = $value['amount'];
                        $rate = $value['rate'];
                        $dept = $value['dept'];
                        $output[] = array($ee,'',$dept,'','','E',$code,(string) $rate, (string) $hours,'','','','','',(string) $amount,'','','','','','','','','','','','','','');
                    }
                }
            }
        }
        //var_dump("OUTPUT", $output);
        $month = $today->format("m");
        $day = $today->format('d');
        $year = $today->format('y');
        $time = $today->format('H-i-s');
        $fileName = "Files/Anchorbuilt_EvoImport-" . $month . "-" . $day . "-" . $year . "-". $time. ".csv";
        $handle = fopen($fileName, 'wb');
        foreach($output as $line){
            fputcsv($handle, $line);
        }
        fclose($handle);
        $_SESSION['fileName'] = $fileName;
        $_SESSION['output'] = "Files Successfully Created";
        $_SESSION['empCount'] = count($data);
        $_SESSION['totPaid'] = array_sum($sum);
        $_SESSION['totHrs'] = round(array_sum($totalHours),2);
        header('Location: index.php');
    }catch(Exception $e){
        $_SESSION['error'] = $e->getMessage();
        header('Location: index.php');
    }
}else{
    $_SESSION['error'] = "<p>No File Was Selected</p>";
    header('Location: index.php');
}
?>