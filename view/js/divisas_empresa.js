
bootbox.setLocale("es");

var empresa_coddivisa = '{$fsc->empresa->coddivisa}';
var empresa_simbolo = '{$fsc->simbolo_divisa()}';

function show_precio(precio, coddivisa)
{
    coddivisa || ( coddivisa = empresa_coddivisa );
    
    if(coddivisa == empresa_coddivisa)
    {
    {if="'{#FS_POS_DIVISA#}'=='right'"}
    return number_format(precio, {#FS_NF0#}, '{#FS_NF1#}', '{#FS_NF2#}') + empresa_simbolo;
    {else}
    return empresa_simbolo + number_format(precio, {#FS_NF0#}, '{#FS_NF1#}', '{#FS_NF2#}');
    {/if}
    }
    else
    {
    return number_format(precio, {#FS_NF0#}, '{#FS_NF1#}', '{#FS_NF2#}');
    }
}
