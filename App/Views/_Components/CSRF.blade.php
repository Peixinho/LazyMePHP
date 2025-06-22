<?php

/**
 * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
 * @author Duarte Peixinho
 */
?>
<input type="hidden" name="csrf_token" value="<?=\Core\Security\CsrfProtection::getCurrentToken()?>"/>
