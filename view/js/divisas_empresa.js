
bootbox.setLocale("es");

function show_precio(precio, coddivisa)
{
    coddivisa || ( coddivisa = '{$fsc->empresa->coddivisa}' );
    
    if(coddivisa == '{$fsc->empresa->coddivisa}')
    {
    {if="FS_POS_DIVISA=='right'"}
    return number_format(precio, {#FS_NF0#}, '{#FS_NF1#}', '{#FS_NF2#}')+' {$fsc->simbolo_divisa()}';
    {else}
    return '{$fsc->simbolo_divisa()}'+number_format(precio, {#FS_NF0#}, '{#FS_NF1#}', '{#FS_NF2#}');
    {/if}
    }
    else
    {
    return number_format(precio, {#FS_NF0#}, '{#FS_NF1#}', '{#FS_NF2#}');
    }
}
