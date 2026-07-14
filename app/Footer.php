<?php
declare(strict_types=1);

function tinymaker_connect_footer_css(): string
{
    return '.siteFooter{border-top:1px solid var(--line);margin-top:28px;padding-top:16px;color:var(--muted);font-size:13px;line-height:1.55}.siteFooter p{margin:0 0 8px}.siteFooter a{color:var(--accent);text-decoration:none}.siteFooter a:hover{text-decoration:underline}';
}

function tinymaker_connect_footer(): string
{
    return '<footer class="siteFooter">'
        . '<p><a href="https://github.com/Briadark/TinyMakerConnect">TinyMakerConnect</a> is created and maintained by Brian Karmelk (<a href="https://github.com/Briadark">@Briadark</a>). It\'s an extension to the <a href="https://github.com/slibbinas/TinyMakerWifi">TinyMakerWifi firmware</a> by Viktoras Sidlauskas (<a href="https://github.com/slibbinas">@slibbinas</a>).</p>'
        . '<p>TinyMaker hardware CC BY-NC-SA 4.0 &middot; Not affiliated with TinyMaker3D — but they like what we do 🙂</p>'
        . '</footer>';
}