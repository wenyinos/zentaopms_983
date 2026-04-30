<?php
 die();
?>

09:53:31 ERROR: '.git/config' illegal.  in framework/base/router.class.php on line 1275, last called by framework/base/router.class.php on line 1182 through function checkModuleName.
 in framework/base/router.class.php on line 2196 when visiting .git/config

09:53:31 Creating default object from empty value in config/filter.php on line 208 when visiting .git/config

10:44:29 ERROR: '.git/config' illegal.  in framework/base/router.class.php on line 1275, last called by framework/base/router.class.php on line 1182 through function checkModuleName.
 in framework/base/router.class.php on line 2196 when visiting .git/config

10:44:29 Creating default object from empty value in config/filter.php on line 208 when visiting .git/config

17:19:55 session_name(): Session name cannot be changed after headers have already been sent (sent from /home/zemi/MyDev/zentaopms_983/framework/helper.class.php on line 20) in /home/zemi/MyDev/zentaopms_983/framework/base/router.class.php on line 815 when visiting 

17:19:55 session_start(): Session cannot be started after headers have already been sent (sent from /home/zemi/MyDev/zentaopms_983/framework/helper.class.php on line 20) in /home/zemi/MyDev/zentaopms_983/framework/base/router.class.php on line 817 when visiting 

17:19:55 htmlspecialchars(): Passing null to parameter #1 ($string) of type string is deprecated in /home/zemi/MyDev/zentaopms_983/framework/base/router.class.php on line 2242 when visiting 

17:19:55 Uncaught Error: Class "PDO" not found in /home/zemi/MyDev/zentaopms_983/framework/base/router.class.php:2125
Stack trace:
#0 /home/zemi/MyDev/zentaopms_983/framework/base/router.class.php(2103): baseRouter->connectByPDO()
#1 /home/zemi/MyDev/zentaopms_983/framework/base/router.class.php(369): baseRouter->connectDB()
#2 /home/zemi/MyDev/zentaopms_983/framework/base/router.class.php(393): baseRouter->__construct()
#3 Command line code(7): baseRouter::createApp()
#4 {main}
  thrown in /home/zemi/MyDev/zentaopms_983/framework/base/router.class.php on line 2125 when visiting 

17:19:55 Constant E_STRICT is deprecated since 8.4, the error level was removed in /home/zemi/MyDev/zentaopms_983/framework/base/router.class.php on line 2264 when visiting 

17:35:22 session_name(): Session name cannot be changed after headers have already been sent (sent from /home/zemi/MyDev/zentaopms_983/framework/helper.class.php on line 20) in /home/zemi/MyDev/zentaopms_983/framework/base/router.class.php on line 815 when visiting 

17:35:22 session_start(): Session cannot be started after headers have already been sent (sent from /home/zemi/MyDev/zentaopms_983/framework/helper.class.php on line 20) in /home/zemi/MyDev/zentaopms_983/framework/base/router.class.php on line 817 when visiting 

17:35:22 Uncaught Error: Class "PDO" not found in /home/zemi/MyDev/zentaopms_983/framework/base/router.class.php:2125
Stack trace:
#0 /home/zemi/MyDev/zentaopms_983/framework/base/router.class.php(2103): baseRouter->connectByPDO()
#1 /home/zemi/MyDev/zentaopms_983/framework/base/router.class.php(369): baseRouter->connectDB()
#2 /home/zemi/MyDev/zentaopms_983/framework/base/router.class.php(393): baseRouter->__construct()
#3 Command line code(7): baseRouter::createApp()
#4 {main}
  thrown in /home/zemi/MyDev/zentaopms_983/framework/base/router.class.php on line 2125 when visiting 
