<div class="content-view-full global-view-full">
    <h1>{"RSS exports"|i18n("design/standard/rss/list")}</h1>

    <ul>
        {foreach $legacy_rss_export_list as $item}
            <li>
                <a href={concat("feed/rss/",$item.access_url)|ezurl}>{$item.title|wash}</a>
                {$item.description|wash} {*({"Version"|i18n("design/standard/rss/list")} {$item.rss_version|wash}) *} -
                <a href={concat("feed/rss/",$item.access_url)|ezurl}>{concat("feed/rss/",$item.access_url)|ezurl(no,full)|wash}</a>
            </li>
        {/foreach}
    </ul>
</div>