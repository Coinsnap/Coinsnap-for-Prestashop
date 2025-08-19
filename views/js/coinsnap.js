/**
 * Copyright since 2023 Coinsnap
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 *
 * @author    Coinsnap <dev@coinsnap.io>
 * @copyright Since 2023 Coinsnap
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */
jQuery(document).ready(function ($) {
    
    if($('#coinsnap_provider').length){
        
        setProvider();
        $('#coinsnap_provider').change(function(){
            setProvider();
        });
    }
    
    function setProvider(){
        if($('#coinsnap_provider').val() !== 'btcpay'){
            $('.form-group.btcpay').hide();
            $('.form-group.btcpay input[type=text]').removeAttr('required');
            $('.form-group.coinsnap').show();
            $('.form-group.coinsnap input[type=text]').attr('required','required');
        }
        else {
            $('.form-group.coinsnap').hide();
            $('.form-group.coinsnap input[type=text]').removeAttr('required');
            $('.form-group.btcpay').show();
            $('.form-group.btcpay input[type=text]').attr('required','required');
        }
    }
});

