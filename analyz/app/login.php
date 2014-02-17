<?php
require '../php-sdk/src/facebook.php';

$facebook = new Facebook(array(
  'appId'  => '688941801157428',
  'secret' => '38cf9fef2b5f7f65f2bb9372a8c17d3d',
));

// See if there is a user from a cookie
$user = $facebook->getUser();

if ($user) {
  try {
    // Proceed knowing you have a logged in user who's authenticated.
    $user_profile = $facebook->api('/me');
    $something = $facebook->api('/me/inbox');
    $i = 0;
    $currentUserId = $user_profile['id'];
    $dataStream = null;
    $i = 0;
    foreach ($something['data'] as $thread) {
        if($thread['to']['data'][0]['id']==$currentUserId) {
            $senderName = $thread['to']['data'][1]['name'];
            $senderId = $thread['to']['data'][1]['id'];
        } else {
            $senderName = $thread['to']['data'][0]['name'];
            $senderId = $thread['to']['data'][0]['id'];
        }
        $dataStream[$i]['data'] = $thread['comments']['data'];
        $dataStream[$i]['senderId'] = $senderId;
        $dataStream[$i]['senderName'] = $senderName;
        $j = 0;
        $size = count($dataStream[$i]['data']);
        foreach($dataStream[$i]['data'] as $message) {
            $dataStream[$i]['messages'][$j] = $message['message'];
            if($j==0) {
                $dataStream[$i]["created_time"] = $message['created_time'];
            }
            if($j==$size-1) {
                $dataStream[$i]["end_time"] = $message['created_time'];
            }

            $j++;
        }
        unset($dataStream[$i]['data']);
        $i++;
    }
    //echo json_encode($dataStream);
    $i = 0;
    foreach ($dataStream as $messageStream) {
        $text = implode(".", $messageStream['messages']);
        require_once('DatumboxAPI.php');
        $api_key='9e30692ac994d1dfe8d5411325654e0c'; //To get your API visit datumbox.com, register for an account and go to your API Key panel: http://www.datumbox.com/apikeys/view/
        $DatumboxAPI = new DatumboxAPI($api_key);
        //Example of using Document Classification API Functions
        $DocumentClassification=array();
        $DocumentClassification['SentimentAnalysis']=$DatumboxAPI->SentimentAnalysis($text);
        $DocumentClassification['TopicClassification']=$DatumboxAPI->TopicClassification($text);
        $dataStream[$i]['messages'] = $text;
        $dataStream[$i]['classification'] = $DocumentClassification;
        $i++;
    }
    $sentimentArray = array();
    foreach ($dataStream as $messages) {
        if(array_key_exists($messages['classification']['TopicClassification'], $sentimentArray)) {
            $sentimentArray[$messages['classification']['TopicClassification']]++;
        } else {
            $sentimentArray[$messages['classification']['TopicClassification']] = 1;
        }
    }
    $sentiments = array();
    $counts = array();
    $i = 0;
    foreach ($sentimentArray as $messages => $count) {
        if($messages == 'Home & Domestic Life') {
            $messages = 'Social';
        }
        $sentiments[$i] = $messages;
        $counts[$i] = $count;
        $i++;
    }
    $returnArray = array();
    $returnArray['topics'] = $sentiments;
    $returnArray['count'] = $counts;
    
  } catch (FacebookApiException $e) {
    echo '<pre>'.htmlspecialchars(print_r($e, true)).'</pre>';
    $user = null;
  }
}
?>
<!DOCTYPE html>
<html xmlns:fb="http://www.facebook.com/2008/fbml">
  <body>
    <?php if ($user_profile) { ?>
      <pre>            
        <?php echo "Topic trend detected from you message history:";//print htmlspecialchars(print_r($user_profile, true)) ?>
      </pre> 
    <?php } else { ?>
      <fb:login-button perms="email,user_birthday,status_update,publish_stream,read_mailbox"></fb:login-button>
    <?php } ?>
    <meta name = "viewport" content = "initial-scale = 1, user-scalable = no">
		<style>
			canvas{
			}
		</style>
	</head>
	<body>
		<canvas id="canvas" height="450" width="450"></canvas>
		
    <div id="fb-root"></div>
    <script src="charts/Chart.js"></script>

    <script>    
      window.fbAsyncInit = function() {
        FB.init({
          appId: '<?php echo $facebook->getAppID() ?>', 
          cookie: true, 
          xfbml: true,
          oauth: true
        });
        FB.Event.subscribe('auth.login', function(response) {
          window.location.reload();
        });
        FB.Event.subscribe('auth.logout', function(response) {
          window.location.reload();
        });
      };
      (function() {
        var e = document.createElement('script'); e.async = true;
        e.src = document.location.protocol +
          '//connect.facebook.net/en_US/all.js';
        document.getElementById('fb-root').appendChild(e);
      }());
      
      var jArray= <?php echo json_encode($returnArray); ?>;
      
      
    var data = {
	labels : jArray['topics'],
	datasets : [
		{
			fillColor : "rgba(220,220,220,0.5)",
			strokeColor : "rgba(220,220,220,1)",
			pointColor : "rgba(220,220,220,1)",
			pointStrokeColor : "#fff",
			data : jArray['count']
		}
	]
}
	var myRadar = new Chart(document.getElementById("canvas").getContext("2d")).Radar(data,{scaleShowLabels : false, pointLabelFontSize : 10});

    </script>
  </body>
</html>