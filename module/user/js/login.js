// Prevent login page show in a iframe modal
if(window.self !== window.top) window.top.location.href = window.location.href;

function setForm(){}
$(document).ready(function()
{
    /* login panel 垂直居中（10.1 布局）*/
    var $login = $('#login');
    if($login.length)
    {
        var adjustPanelPos = function()
        {
            var bestTop = Math.max(0, Math.floor($(window).height() - $login.outerHeight()) / 2);
            $login.css('margin-top', bestTop);
        };
        adjustPanelPos();
        $(window).on('resize', adjustPanelPos);
    }

    $('#account').focus();

    $("#langs li > a").click(function()
    {
        selectLang($(this).data('value'));
    });

    var mBtn = $('#mobile');
    mBtn.popover({html: true, container: 'body'}).click(function(event)
    {
        event.stopPropagation();
        $(document).one('click', function(){$('#mobile').popover('hide');});
    });
    mBtn.attr('title', mBtn.attr('data-original-title'));
    $('#loginPanel form').submit(function()
    {
        var password = $('input:password').val();
        var rand = $('input#verifyRand').val();
        if(password.length != 32 && typeof(md5) == 'function') $('input:password').val(md5(md5(password) + rand));
    });
})
