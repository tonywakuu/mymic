/*************Backend User Module for Social Logins & Sign-ups*************/

/***Prerequisites***/

Make sure you have installed all these prerequisites on your development machine.
1.PHP 5.3 or above.

2.curl extension for PHP.

/***Integration***/
1. For web login:

Simply include user.php (require_once __DIR__ . '/controller/user.php';) from the controller section of nucleo plugin to reach all the functionalities available
for login & sign-up at social accounts like Google, Instagram, Facebook, Twitter and may more to come.
Your application should run on the 9000 port so in your browser just go to http://localhost/<your-app>/nucleo/

2. For backend application login providers

Simply include user.php (require_once __DIR__ . '/controller/user.php';) from the controller section of nucleo plugin to reach all the functionalities available
for login & sign-up at social accounts like Google, Instagram, Facebook, Twitter and may more to come.
Your application should run on the REST console using end point http://localhost/<your-app>/<api-name>

/***SMTP setup***/

To setup SMTP with nucleo in order to send out the emails for forgot password etc, you must follow the following steps:
1. Install & configure sendmail
   a) If sendmail is not installed, do install it:
            apt-get install sendmail
   b) Configure hosts file correctly: 
            nano /etc/hosts
      And make sure the line looks like this:
            127.0.0.1 localhost localhost.localdomain yourhostnamehere
   c) Reload /etc/hosts, so that the previous changes take effect
            sudo /etc/init.d/networking restart that works but seems to be deprecated, better use:
            /etc/init.d/networking stop
            /etc/init.d/networking start

   d) Run the sendmail config and answer ‘Y’ to everything: 
            sendmailconfig

2. Go to utils/sendMail.php and provide your preferred SMTP details in order to activate the use of email.

In case of any errors or difficulty uncomment the $mail->SMTPDebug to know more about the issue.

Follow given links while using gmail SMTP details.

https://www.google.com/settings/u/1/security/lesssecureapps
https://accounts.google.com/b/0/DisplayUnlockCaptcha
https://security.google.com/settings/security/activity?hl=en&pli=1

/*************Backend Search Module to perform filtering,searching & sorting on defined entities*************/

/***Integration***/
1. For search,sort and filter:

Simply include search.php (require_once __DIR__ . '/controller/search.php';) and pass following params in order to
to achieve desired results.

$searchData = array(
    'entity' => '<entity>',
    'searchMethod' => '<AND/OR>',
    'searchParameters' => array('<entity_attribute>' => '<SEARCH TEXT>',.....),
    'sortParameters' => array('<entity_attribute>' => <1/-1>,.....),//1 for ASC and -1 for DESC
    'filter' => array('<entity_attribute>' => <1/0>,.....),//1 to include and 0 to exclude
    'page' => (int) '<PAGE NUMBER>',
    'docPerPage' => (int) '<COUNT ON EACH PAGE>',
);

2. Location based search:
For performing location based search we need to have longitude and latitude stored in database in number format.
* Index of the field should be 2d in case of mongodb
$searchData = array(
    'entity' => '<entity>',
    'searchMethod' => '<AND/OR>',
    'searchParameters' => array('<entity_attribute>' => '<SEARCH TEXT>','<location>'=>array('long'=><float value>,'lat'=><float value>,'radius'=><float value>)),
    'sortParameters' => array('<entity_attribute>' => <1/-1>,.....),//1 for ASC and -1 for DESC
    'filter' => array('<entity_attribute>' => <1/0>,.....),//1 to include and 0 to exclude
    'page' => (int) '<PAGE NUMBER>',
    'docPerPage' => (int) '<COUNT ON EACH PAGE>',
);

/*************Enabling push notifications for the registered Android and iOS device Id's*************/

/***Integration***/
1. Simply include pushNotifications.php (require_once __DIR__ . '/model/pushNotifications.php';)
2. Provide simple configuration options in the file.

// API access key from Google API's Console
define('ANDROID_API_ACCESS_KEY', 'YOUR KEY GOES HERE');
//IOS pass phrase
define('IOS_PASS_PHRASE', 'YOUR PHRASE GOES HERE');
//Path to iOS certificate
define('IOS_CERTIFICATE_PATH', realpath(__DIR__ . '/..') . '/config/<cretificate.pem name goes here>');