<h1>{"RSS exports"|i18n("design/standard/rss/list")}</h1>

<table class="list" width="100%" cellpadding="0" cellspacing="0" border="0">
    {foreach $legacy_rss_export_list as $item}
    <tr>
        <td><a href={concat("feed/rss/",$item.access_url)|ezurl}>{$item.title|wash} ({"Version"|i18n("design/standard/rss/list")} {$RSSExport:item.rss_version|wash})</a></td>
    </tr>
    {/foreach}
</table>
