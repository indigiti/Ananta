<?php
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/account', PHP_URL_PATH) ?: '/account';
$nav = [['/account','Overview'],['/account/sourcing','Sourcing requests'],['/account/addresses','Addresses'],['/account/orders','Orders']];
?>
<aside class="account-side" aria-label="Account navigation">
<?php foreach($nav as [$href,$label]): $active = $href==='/account' ? $uri==='/account' : str_starts_with($uri,$href); ?>
<a class="<?= $active?'active':'' ?>" href="<?= e(url($href)) ?>"><?= e($label) ?></a>
<?php endforeach; ?>
<form method="post" action="<?=e(url('/account/logout'))?>"><?=csrf_field()?><button type="submit">Sign out</button></form>
</aside>
