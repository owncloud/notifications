<?php print_unescaped($_['subject']); ?>


<?php print_unescaped($_['message']); ?>


<?php print_unescaped($l->t('Go to %s to check the notification', [$_['serverUrl']])); ?>

--
<?php p($theme->getName() . ' - ' . $theme->getSlogan()); ?>
<?php print_unescaped("\n".$theme->getBaseUrl());
