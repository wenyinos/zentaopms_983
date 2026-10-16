$(function()
{
    $('.c-boards').each(function()
    {
        var $boardsWrapper = $(this).find('.boards-wrapper');
        if($boardsWrapper.height() < $(this).height())
        {
            $boardsWrapper.find('.boards').css('height', $(this).height());
        }
    });

    var boardID  = '';
    var onlybody = config.requestType == 'GET' ? "&onlybody=yes" : "?onlybody=yes";
    $.cookie('selfClose', 0, {expires:config.cookieLife, path:config.webRoot});
    var $kanban = $('#kanban');

    var statusMap =
    {
        task:
        {
            wait   : {doing: 'start', done: 'finish', cancel: 'cancel'},
            doing  : {done: 'finish', pause: 'pause'},
            pause  : {doing: 'activate', done: 'finish', cancel: 'cancel'},
            done   : {doing: 'activate', closed: 'close'},
            cancel : {doing: 'activate', closed: 'close'},
            closed : {doing: 'activate'}
        },
        bug:
        {
            wait   : {done: 'resolve', cancel: 'resolve'},
            doing  : {},
            done   : {wait: 'activate', closed: 'close'},
            cancel : {wait: 'activate', closed: 'close'},
            closed : {wait: 'activate'}
        }
    };

    // Get scrollbar width
    var getScrollbarWidth = function ()
    {
        var outer = document.createElement("div");
        outer.style.visibility = "hidden";
        outer.style.width = "100px";
        outer.style.msOverflowStyle = "scrollbar"; // needed for WinJS apps
    
        document.body.appendChild(outer);
    
        var widthNoScroll = outer.offsetWidth;
        // force scrollbars
        outer.style.overflow = "scroll";
    
        // add innerdiv
        var inner = document.createElement("div");
        inner.style.width = "100%";
        outer.appendChild(inner);        
    
        var widthWithScroll = inner.offsetWidth;

        // remove divs
        outer.parentNode.removeChild(outer);
    
        return widthNoScroll - widthWithScroll;
    };

    var scrollbarWidth = getScrollbarWidth();
    var fixBoardWidth = function()
    {
        var $table = $kanban.find('.table');
        var kanbanWidth = $table.width();
        var $cBoards = $table.find('thead>tr>th.c-board:not(.c-side)');
        var boardCount = $cBoards.length;
        var $cSide = $table.find('thead>tr>th.c-board.c-side');
        var totalWidth = kanbanWidth - scrollbarWidth;
        if ($cSide.length) totalWidth = totalWidth - ($cSide.outerWidth() + 5);
        var cBoardWidth = Math.floor(totalWidth/boardCount) - 16;
        $cBoards.not(':last').width(cBoardWidth);
        $cBoards.first().width(cBoardWidth + 5);
        $kanban.find('.boards > .board').width(cBoardWidth + 16 - 22);
    };
    fixBoardWidth();
    $(window).on('resize', fixBoardWidth);

    var refresh = function()
    {
        var selfClose = $.cookie('selfClose');
        $.cookie('selfClose', 0, {expires:config.cookieLife, path:config.webRoot});
        if(selfClose == 1) $kanban.load(location.href + ' #kanban', fixBoardWidth);
    };

    var kanbanModalTrigger = new $.zui.ModalTrigger({type: 'iframe', width:800});
    var lastOperation;

    /* 保存同列拖拽产生的人工顺序（跨列拖拽仍走状态流转表单）。 */
    var saveOrder = function()
    {
        var order = {};
        $kanban.find('tr[data-lane]').each(function()
        {
            var laneKey = $(this).data('lane');
            $(this).find('.board').each(function()
            {
                var status = $(this).data('type');
                var items  = [];
                $(this).find('.board-item').each(function()
                {
                    var $item = $(this);
                    items.push($item.data('type') + '-' + $item.data('id'));
                });
                if(items.length) order[laneKey + '|' + status] = items;
            });
        });

        $.post($.createLink('project', 'kanbanOrder', 'projectID=' + projectID),
            {type: kanbanType, order: JSON.stringify(order)},
            function(data)
            {
                if(!data || data.result != 'success') $.zui.messager.danger(kanbanOrderFailed);
            },
            'json');
    };

    var dropTo = function(id, from, to, type)
    {
        if(statusMap[type][from] && statusMap[type][from][to])
        {
            lastOperation = {id: id, from: from, to: to};
            kanbanModalTrigger.show(
            {
                url: $.createLink(type, statusMap[type][from][to], 'id=' + id) + onlybody,
                shown:  function(){$('.modal-iframe').addClass('with-titlebar').data('cancel-reload', true)},
                hidden: refresh
            });
        }
        return false;
    };

    $kanban.droppable(
    {
        selector: '.board-item:not(.disabled)',
        target: function($ele)
        {
            var itemType = $ele.data('type');
            var $board = $ele.closest('.board');
            var type = $board.data('type');
            var $targets = $board.siblings('.board').filter(function()
            {
                var typeMap = statusMap[itemType];
                var actionMap = typeMap && typeMap[type];
                return !!actionMap && actionMap[$(this).data('type')];
            });
            /* 允许落回自身所在的列，用于同列内排序。 */
            return $targets.add($board);
        },
        start: function(e)
        {
            $kanban.addClass('dragging');
            e.targets.addClass('can-drop-in');
            var $item = $(e.element).addClass('dragging');
            $item.closest('.boards').addClass('dragging');
        },
        drag: function(e)
        {
            var $item = $(e.element);
            var $target = $(e.target);
            var $holder = $target.find('.board-drag-holder');
            if (!$holder.length) $holder = $('<div class="board-drag-holder"></div>').appendTo($target);
            $kanban.find('.c-board.dragging').removeClass('dragging');
            $kanban.find('.c-board.s-' + $target.data('type')).addClass('dragging');
            $holder.height($item.outerHeight());
        },
        drop: function(e)
        {
            var $item = e.element;
            var $from = $item.closest('.board');
            var $to   = e.target;

            /* 同列拖拽：只调整顺序并落库，不触发状态流转。 */
            if($from[0] === $to[0])
            {
                $item.insertBefore($to.find('.board-drag-holder'));
                saveOrder();
                return;
            }

            var result = dropTo($item.data('id'), $from.data('type'), $to.data('type'), $item.data('type'));
            if(result !== false)
            {
                $item.insertBefore($to.find('.board-drag-holder'));
            }
        },
        finish: function()
        {
            $kanban.removeClass('dragging').find('.can-drop-in').removeClass('can-drop-in');
            $kanban.find('.dragging').removeClass('dragging');
        }
    });

    /* 泳道折叠：点击泳道名切换，折叠状态只存 localStorage（不落库、不影响他人）。 */
    var laneCollapseKey = 'kanbanLanes.' + projectID + '.' + kanbanType;
    var collapsedLanes  = {};
    try { collapsedLanes = JSON.parse(localStorage.getItem(laneCollapseKey) || '{}') || {}; } catch(e) { collapsedLanes = {}; }

    var applyCollapsedLanes = function()
    {
        $kanban.find('tr[data-lane]').each(function()
        {
            $(this).toggleClass('lane-collapsed', !!collapsedLanes[$(this).data('lane')]);
        });
    };
    applyCollapsedLanes();

    $kanban.on('click', 'td.c-side', function(e)
    {
        if($(e.target).closest('a').length) return;       // 点泳道内的链接不触发折叠

        var $tr     = $(this).closest('tr[data-lane]');
        var laneKey = String($tr.data('lane'));

        if(collapsedLanes[laneKey]) delete collapsedLanes[laneKey];
        else collapsedLanes[laneKey] = 1;

        try { localStorage.setItem(laneCollapseKey, JSON.stringify(collapsedLanes)); } catch(e) {}
        applyCollapsedLanes();
    });

    $kanban.on('click', '.kanbaniframe', function(e)
    {
        var $link = $(this);
        new $.zui.ModalTrigger($.extend(
        {
            type: 'iframe',
            url: $link.attr('href'),
            width: '80%',
        }, $link.data())).show(
        {
            shown:  function(){$('.modal-iframe').data('cancel-reload', true)},
            hidden: refresh
        });
				return false;
    });

    $.extend({'closeModal':function(callback, location)
    {
        kanbanModalTrigger.close();
        if(callback && $.isFunction(callback)) callback();
    }});
});
