<?php if ($_['message'] !== ''): ?>
<?php print_unescaped($_['message']); ?>


<?php endif; ?>
<?php print_unescaped($l->t('See %s for more information', [$_['serverUrl']])); ?>


--
<?php p($theme->getName() . ' - ' . $theme->getSlogan()); ?>
<?php print_unescaped("\n".$theme->getBaseUrl());
