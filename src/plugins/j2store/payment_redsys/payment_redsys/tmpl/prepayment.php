<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  J2Store.payment_redsys
 *
 * @copyright Copyright (C) 2019 J2Store All rights reserved.
 * @copyright Copyright (C) 2025 Hepta Technologies SL. All rights reserved.
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPLv3 or later
 * @website https://extensions.hepta.es
 */

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Filesystem\Path;

\defined('_JEXEC') or die('Restricted access');
?>

<?php echo Text::_($vars->onbeforepayment_text); ?>
<?php if(isset($vars->fields) && count($vars->fields) ): ?>
<form action='<?php echo $vars->post_url; ?>' method='post'>
<?php foreach ($vars->fields as $key => $value) : ?>
        <input type="hidden" name="<?php echo $key; ?>" value="<?php echo $value; ?>" />
 <?php endforeach;?> 
	 <?php $image = $this->params->get('display_image', ''); ?>
         <?php if(!empty($image)): ?>
         	<span class="j2store-payment-image">
				<img class="payment-plugin-image payment_authrizedotnet" src="<?php echo Uri::root().Path::clean($image); ?>" />
			</span>
			<br />
		<?php endif; ?>
	 <input type="submit" class="btn btn-primary button" value="<?php echo Text::_($vars->button_text); ?>" />
</form>
<?php else: ?>
	<?php echo Text::_($vars->onerrorpayment_text); ?>
<?php endif; ?>
