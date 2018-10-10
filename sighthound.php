<pre>
<?php
// Debug
set_time_limit(0);
ignore_user_abort(1);
error_reporting(E_ALL);
ini_set('display_errors', 'on');

// Setup
//require_once("../lockfile.php");
require_once('../vendor/autoload.php');
require_once("../creds.php");
require_once("../extra-funcs.php");


// read incoming info and grab the chatID
$content = file_get_contents("php://input");
$update = json_decode($content, true);
$chatID = $update["message"]["chat"]["id"];


use Telegram\Bot\Api;
$telegram = new Api($sighthoundBotKey);
/*
$response = $telegram->sendMessage([
  'chat_id' => $chatID,
  'text' => $content
]);
*/

$fileID = "";
if (isset($update['message']['photo']))  //If it's a photo, proceed
{
  $fileID = $update['message']['photo'][ sizeof($update['message']['photo'] )-1]['file_id']; // Look at the array of the photo, pick the last (biggest) file
}
else {
    $response = $telegram->sendMessage([
      'chat_id' => $chatID,
      'text' => "Send me a photo to evaluate!"
    ]);
  //Send some buttons
  die();
}

if (!empty($fileID))
  $fileDetails = $telegram->getFile(['file_id' => $fileID]);

//$sighthoundBotKey = "[key]";
$filePath = ("https://api.telegram.org/file/bot" . $sighthoundBotKey .'/'. $fileDetails['file_path']);

$file=file_get_contents($filePath);
file_put_contents($chatID . ".jpg", $file);


// Submit image for processing
$url = 'https://dev.sighthoundapi.com/v1/detections';
$data ='{
		  "image": "' . base64_encode($file) . '"
}';

$ch = curl_init();
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
  'Content-Type: application/json',
  'Connection: Keep-Alive',
  'X-Access-Token: ' . $sighthound
));
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$result = curl_exec($ch);
if(curl_errno($ch) !== 0) {
    error_log('cURL error when connecting to ' . $url . ': ' . curl_error($ch));
}

curl_close($ch);
$json=json_decode($result);
// Image data returned

/*
$response = $telegram->sendMessage([
  'chat_id' => $chatID,
  'text' => $result
]);
*/

//Load image for editing
$image=imagecreatefromjpeg($chatID . ".jpg");

$message = "";
$i=1;

//Each object detected
foreach ($json->objects as $segment) {
  if ($segment->type != "face")
    continue;  //Skip non-faces

  $thisMessage= "Face " . $i . ": ";
  // Gender
  $thisMessage.= $segment->attributes->gender . " (" . round((float)$segment->attributes->genderConfidence * 100) . "%)\n";
  // Age
  $thisMessage.= "Age: " . $segment->attributes->age . " (" . round((float)$segment->attributes->ageConfidence * 100) . "%)\n";
  // Emotion
  $thisMessage.= "Emotion: " . $segment->attributes->emotion . " (" . round((float)$segment->attributes->emotionConfidence * 100) . "%)\n";

  $i++;

	if ($segment->attributes->gender == "male")
		$rgb = hex2rgb("#4682B4");
  else
    $rgb = hex2rgb("#FF69B4");

	/* Draw on Image */
  $color = imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);
  imagesetthickness($image, 5);
	$fontSize = 15;

  $face = $segment->boundingBox;
	//Draw box
	imagerectangle($image, $face->x, $face->y, $face->x + $face->width, $face->y + $face->height, $color);
	//Draw text overlay
	imagettftext($image, $fontSize, 0, $face->x, $face->y - ($fontSize*3+3), $color, "FSEX300.ttf",	$thisMessage);

  $message.=$thisMessage;
}

/*
$response = $telegram->sendMessage([
  'chat_id' => $chatID,
  'text' => $message
]);


die();
*/
imagejpeg($image, $chatID . ".jpg");

$response = $telegram->sendPhoto([
	'chat_id' => $chatID,
	'photo' => $chatID . ".jpg",
	'caption' => $message
]);

?>
</pre>
