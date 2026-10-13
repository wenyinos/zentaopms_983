/**
 * WenYinOS 10.1 UI 兼容垫片
 * ------------------------------------------------------------
 * 本文件为 10.1 界面骨架（header/footer 模板）提供 ZUI 1.7（9.8.3 自带）缺失的
 * 少量新 API。10.1 骨架调用了 ZUI 1.8.1 才有的接口，而本项目保留 9.8.3 的
 * all.js（含 ZUI 1.7 + 全部老页面依赖函数），因此在此补齐。
 *
 * 加载顺序：all.js 之后（header.lite.html.php）。
 * 原则：只做"补缺"，不覆盖任何已存在的函数；如后续整体升级 ZUI 可整文件删除。
 */
(function($)
{
    /* --- 侧栏接口：10.1 footer 无条件调用 $.initSidebar()，老页面无 #sidebar，置空即可 --- */
    if(!$.initSidebar) $.initSidebar = function(){ return false; };
    if(!$.toggleSidebar) $.toggleSidebar = function(){};

    /* --- 搜索框：10.1 printSearchBox 输出了 #searchInput/#searchTypeMenu 结构 --- */
    $.setSearchType = function(type, noFocus)
    {
        var $hidden = $('#searchType');
        if(!type) type = $hidden.val();
        type = type || 'bug';
        $hidden.val(type);

        var $menu = $('#searchTypeMenu');
        $menu.find('li.selected').removeClass('selected');
        var $a = $menu.find('a[data-value="' + type + '"]');
        $a.parent().addClass('selected');
        $('#searchTypeName').text($a.text());

        if(!noFocus) $('#searchInput').focus();
    };

    $.gotoObject = function(type, value)
    {
        if(!type)  type  = $('#searchType').val();
        if(!value) value = $('#searchInput').val();
        if(value && type)
        {
            var method = (type === 'testsuite') ? 'library' : 'view';
            window.location.href = createLink(type, method, 'id=' + value);
        }
    };

    /* --- Ctrl+G 聚焦搜索框（10.1 快捷键行为） --- */
    $(document).on('keydown', function(e)
    {
        if(e.ctrlKey && e.keyCode === 71)
        {
            $('#searchInput').val('').focus();
            e.stopPropagation();
            e.preventDefault();
        }
    });

    /* --- 老搜索框兼容：9.8.3 模板输出 #typeSelector/#searchQuery，绑定其下拉 --- */
    $(function()
    {
        $('#typeSelector a').click(function()
        {
            $('#typeSelector li.active').removeClass('active');
            var $this = $(this);
            $this.closest('li').addClass('active');
            $('#searchType').val($this.data('value'));
            $('#searchTypeName').text($this.text());
        });
    });

    /* --- 页面淡入：10.1 骨架的 #main.fade 需加 .in 才显示（ZUI 1.8 内置行为，1.7 无） --- */
    $(function()
    {
        var $main = $('#main, #mainContent, #mainRow, .auto-fade-in');
        if($main.length && $main.hasClass('fade')) setTimeout(function(){$main.addClass('in');}, $main.data('fadeTime') || 200);
    });
})(jQuery);
