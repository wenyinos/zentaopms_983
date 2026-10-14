/**
 * WenYinOS 10.1 UI 兼容垫片
 * ------------------------------------------------------------
 * 前端框架已升级为 ZUI 1.8.1（10.1 自带版本），骨架与页面使用的新 API
 * （initSidebar / setSearchType / gotoObject / timeSpanControl / fade 淡入 等）
 * 均由 all.js 原生提供，本文件不再重复实现。
 *
 * 保留内容仅为「9.8.3 老页面残留调用」所需的少量函数：
 *   - setOuterBox 等 9.8.3 布局函数（老模板的 #wrap > .outer 体系，10.1 已移除）
 *   - 老搜索框 #typeSelector 的下拉绑定（兼容历史模板片段）
 *
 * 加载顺序：all.js 之后（header.lite.html.php）。
 * 原则：只补 ZUI 未提供的部分；如确认无老页面引用可整文件删除。
 */
(function($)
{
    /* ==========================================================================
     * 一、9.8.3 老页面残留函数垫片
     * 说明：ZUI 1.8.1 移除了 9.8.3 my.full.js 中的下列函数，但极少数老模板/模块
     *       JS 仍会调用（当前确认：company/js/edit.js 调用 setOuterBox）。
     *       这里按 9.8.3 原实现补齐，避免调用处报 TypeError。
     * ========================================================================== */

    /* 调整 .outer 高度（9.8.3 原实现） */
    function adjustOuterSize()
    {
        var side   = $('#wrap .outer > .side');
        var sideH  = side.length ? (side.outerHeight() + $('#featurebar').outerHeight() + 20) : 0;
        var height = Math.max(sideH, $(window).height() - $('#header').outerHeight() - ($('#footer').outerHeight() || 0) - 20);
        if(navigator.userAgent.indexOf('MSIE 8.0') >= 0) height -= 40;
        $('#wrap .outer').css('min-height', height);
    }

    /* 绑定 .outer 尺寸自适应（9.8.3 原实现） */
    if(!window.setOuterBox)
    {
        window.setOuterBox = function()
        {
            var side = $('#wrap .outer > .side');
            side.resize(adjustOuterSize);
            $(window).resize(adjustOuterSize);
            adjustOuterSize();
        };
    }

    /* 树形侧栏：9.8.3 老模板 footer 用 setTreeBox()，10.1 骨架改用 $.initSidebar()（ZUI 原生）。
       当前主线模板已无调用，保留空实现以兼容可能存在的第三方扩展页面。 */
    if(!window.setTreeBox) window.setTreeBox = function(){};

    /* ==========================================================================
     * 二、老搜索框兼容（历史模板片段使用 #typeSelector + #searchQuery）
     * 当前主线已用 10.1 的 #searchTypeMenu/#searchInput，此处仅为历史片段兜底。
     * ========================================================================== */
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
})(jQuery);
