<?php
declare(strict_types=1);

function tinymaker_connect_footer_css(): string
{
    return '.siteFooter{border-top:1px solid var(--line);margin-top:28px;padding-top:16px;color:var(--muted);font-size:13px;line-height:1.55}.siteFooter p{margin:0 0 8px}.siteFooter a{color:var(--accent);text-decoration:none}.siteFooter a:hover{text-decoration:underline}';
}

function tinymaker_connect_footer(): string
{
    return '<footer class="siteFooter">'
        . '<p><a href="https://github.com/Briadark/TinyMakerConnect" target="_blank" rel="noopener">TinyMakerConnect</a>, the Connect service, and the browser flash tool are built and hosted by Brian Karmelk (<a href="https://github.com/Briadark" target="_blank" rel="noopener">@Briadark</a>).</p>'
        . '<p>Did this help you? <a href="https://www.paypal.com/paypalme/Briadark" target="_blank" rel="noopener">Buy Brian a coffee</a>.</p>'
        . '<p>The <a href="https://github.com/slibbinas/TinyMakerWifi" target="_blank" rel="noopener">TinyMakerWifi firmware</a> is maintained by Viktoras Sidlauskas (<a href="https://github.com/slibbinas" target="_blank" rel="noopener">@slibbinas</a>). <a href="https://www.paypal.com/paypalme/Sidlauskas" target="_blank" rel="noopener">Buy Viktoras a coffee</a>.</p>'
        . '<p>With help from Tanner (<a href="https://github.com/Tann2019" target="_blank" rel="noopener">@Tann2019</a>).</p>'
        . '<p>TinyMaker hardware CC BY-NC-SA 4.0 &middot; Not affiliated with TinyMaker3D — but they like what we do 🙂</p>'
        . '</footer>';
}
