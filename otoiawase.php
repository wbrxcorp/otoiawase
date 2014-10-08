<?php
///////////////////////////////////////////////////////////////////////////////
// PHP+AngularJS+Bootstrapお問い合わせメールフォーム
// 設定ここから ///////////////////////////////////////////////////////////////
define("EMAIL_TO", "お問い合わせ内容を受信するメールアドレス"); // 必須
define("EMAIL_FROM", "メールのFrom欄に記載するアドレス"); // 必須

// フォーム本体のHTMLファイルを指定
define("FORM_HTML", "example01.html");

// ページの <title>要素に入るテキスト
define("TITLE", "お問い合わせフォーム");

// お問い合わせ内容を通知するメールの題名
define("EMAIL_SUBJECT", "お問い合わせがありました");

// 自分のCSSを追加する場合には下記のようにSTYLESHEET定数を定義する
// define("STYLESHEET", "./css/my-style.css");

// 設定ここまで ///////////////////////////////////////////////////////////////
mb_language("Japanese");
mb_internal_encoding("UTF-8");
session_start();

$properly_configured = defined("EMAIL_TO") && check_email(EMAIL_TO) && defined("EMAIL_FROM") && check_email(EMAIL_FROM);

function check_email($email) {
  // email address regex from http://blog.livedoor.jp/dankogai/archives/51189905.html
  return preg_match('/^(?:(?:(?:(?:[a-zA-Z0-9_!#\$\%&\'*+\/=?\^`{}~|\-]+)(?:\.(?:[a-zA-Z0-9_!#\$\%&\'*+\/=?\^`{}~|\-]+))*)|(?:"(?:\\[^\r\n]|[^\\"])*")))\@(?:(?:(?:(?:[a-zA-Z0-9_!#\$\%&\'*+\/=?\^`{}~|\-]+)(?:\.(?:[a-zA-Z0-9_!#\$\%&\'*+\/=?\^`{}~|\-]+))*)|(?:\[(?:\\\S|[\x21-\x5a\x5e-\x7e])*\])))$/', $email);
}

function check_xsrf_token() {
  $headers = getallheaders();
  return isset($headers["X-XSRF-TOKEN"]) && $headers["X-XSRF-TOKEN"] == session_id();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!check_xsrf_token()) {
    header("HTTP/1.0 403 Forbidden"); 
    echo "403 Forbidden(Token mismatch)";
    exit();
  }

  header('Content-Type: application/json');
  try {
    if (!$properly_configured) {
      throw new Exception("EMAIL_TO 又は EMAIL_FROM定数が正しく設定されていません");
    }
    $json = file_get_contents('php://input');
    if (strlen($json) > 10240) {
      throw new Exception("送信データが大きすぎます (>10kb)");
    }
    $json = json_decode($json, true);
    $json["user_agent"] = $_SERVER['HTTP_USER_AGENT'];
    $json["remote_addr"] = $_SERVER['REMOTE_ADDR'];

    if (defined("JSON_PRETTY_PRINT") && defined("JSON_UNESCAPED_UNICODE")) {
      $json = json_encode($json, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
    } else {
      $json = print_r($json, TRUE);
    }

    if (!mb_send_mail(EMAIL_TO, EMAIL_SUBJECT, $json, "From: " . EMAIL_FROM)) {
      throw new Exception("システムエラー: メールの送信に失敗しました。");
    }
  }
  catch (Exception $e) {
    $result = Array("success"=>false, "info"=>$e->getMessage());
    echo json_encode($result);
    exit();
  }
  $result = Array("success"=>true, "info"=>null);
  echo json_encode($result);
  session_destroy();
  exit();
}
// else
header('Content-Type: text/html;charset=UTF-8');
if (!function_exists("json_decode")) {
  echo "このPHPは<a href=\"http://php.net/manual/ja/book.json.php\">JSON</a>に対応していません。";
  exit();
}
if (!function_exists("mb_send_mail")) {
  echo "このPHPは<a href=\"http://php.net/manual/ja/mbstring.installation.php\">mbstring</a>に対応していません。";
  exit();
}
$ua = $_SERVER['HTTP_USER_AGENT'];
if ($ua) {
  if (preg_match("/MSIE ([0-9]+)/", $ua, $matches, PREG_OFFSET_CAPTURE, 0)) {
    $ie_version = (int)$matches[1][0];
    if ($ie_version < 9) {
      echo "古いInternet Explorer(バージョン9未満)は使用できません。検出されたバージョン:" . $ie_version;
      exit();
    }
  }
}
setcookie("XSRF-TOKEN", session_id());
?><html lang="ja" ng-app="Otoiawase">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="stylesheet" href="http://netdna.bootstrapcdn.com/bootstrap/latest/css/bootstrap.min.css">
    <?php if (defined("STYLESHEET")) {?><link rel="stylesheet" href="<?php echo STYLESHEET ?>"><?php } ?>
    <script src="http://ajax.googleapis.com/ajax/libs/angularjs/1.2.26/angular.min.js"></script>
    <script src="http://ajax.googleapis.com/ajax/libs/angularjs/1.2.26/angular-resource.min.js"></script>
    <script src="http://cdnjs.cloudflare.com/ajax/libs/angular-ui-bootstrap/0.11.0/ui-bootstrap-tpls.min.js"></script>
    <script language="javascript">
      var INTEGER_REGEXP = /^\-?\d+$/;
      var FLOAT_REGEXP = /^\-?\d+((\.|\,)\d+)?$/;
      angular.module("Otoiawase", ["ngResource","ui.bootstrap"])
      .run(["$rootScope", "$resource","$modal", function($scope, $resource,$modal) {
        function submit() {
          var obj = {};
          angular.forEach($scope, function(value, key) {
            if (key !== "this" && key !== "form" && key.indexOf("__") !== 0) {
              obj[key] = value;
            }
          }, obj);

<?php if ($properly_configured) { ?>
          var modalInstance = $modal.open({
            templateUrl:"progress.html",
            backdrop:"static",keyboard:false
          });
          $resource("<?php echo basename($_SERVER["SCRIPT_FILENAME"])?>").save({}, obj, function(result) {
            modalInstance.close();
            if (result.success) {
              $modal.open({templateUrl:"done.html", backdrop:"static",keyboard:false});
            } else {
              $scope.__message = result.info;
              $modal.open({templateUrl:"error.html",scope:$scope});
            }
          }, function(result) { 
            modalInstance.close();
            $scope.__message = "HTTPエラー: " + result.data;
            $modal.open({templateUrl:"error.html",scope:$scope});
          });
<?php } else { ?>
          $scope.__values = obj;
          $modal.open({templateUrl:"show-email.html",scope: $scope });
<?php } ?>
        }
        $scope.submit = function() {
	  submit();
	}
        $scope.confirm = function() {
          $modal.open({templateUrl:"confirm.html",scope:$scope}).result.then(function() {
            submit();
          });
        }
        $scope.openModal = function(template) {
          $modal.open({templateUrl:template,scope:$scope});
        }
      }])
      .directive("match", ["$parse", function($parse) {
        return {
          require: 'ngModel',
          link: function(scope, elem, attrs, ctrl) {
            scope.$watch(function() {
              var target = $parse(attrs.match)(scope);
              return !ctrl.$modelValue || target === ctrl.$modelValue;
            }, function(currentValue) {
              ctrl.$setValidity('mismatch', currentValue);
            });
          }
        }
      }])
      .directive('integer', function() {
        return {
          require: 'ngModel',
          link: function(scope, elm, attrs, ctrl) {
            ctrl.$parsers.unshift(function(viewValue) {
              if (!viewValue || INTEGER_REGEXP.test(viewValue)) {
                ctrl.$setValidity('integer', true);
                return viewValue;
              } else {
                ctrl.$setValidity('integer', false);
                return undefined;
              }
            });
          }
        };
      });
    </script>
    <title><?php echo TITLE;?></title>
  </head>
  <body>
<?php if (!@include(FORM_HTML)) {?>
    <span class="text-danger">エラー: <?php echo FORM_HTML?>が設置されていません。</span><br>
 <?php }?>
    <script type="text/ng-template" id="progress.html">
      <div class="modal-header">送信中...</div>
      <div class="modal-body">
        <progressbar class="progress-striped active" animate="false" value="100">
        </progressbar>
      </div>
    </script>
    <script type="text/ng-template" id="error.html">
      <div class="modal-header">
        <button type="button" class="close" ng-click="$dismiss()">×</button>
        エラー
      </div>
      <div class="modal-body">
        {{__message}}
      </div>
      <div class="modal-footer">
        <button class="btn btn-danger" ng-click="$close()">閉じる</button>
      </div>
    </script>
<?php if (!$properly_configured) { ?>
    <script type="text/ng-template" id="show-email.html">
      <div class="modal-header">
        <button type="button" class="close" ng-click="$dismiss()">×</button>
        <?php echo EMAIL_SUBJECT ?>
      </div>
      <div class="modal-body">
        <p>{{ __values | json}}</p>
	<div class="alert alert-danger">送信先メールアドレス(EMAIL_TO) 又は 送信元メールアドレス(EMAIL_FROM)が正しく設定されていないため、メールが送信される代わりにこのウィンドウが表示されています。</div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-default" ng-click="$close()">閉じる</button>
      </div>
    </script>
<?php } ?>
  </body>
</html>
<!--
The MIT License (MIT)

Copyright (c) 2014 Tomoatsu Shimada/Walbrix Corporation

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
-->
