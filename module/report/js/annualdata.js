$(function()
{
    if(typeof echarts === 'undefined')
    {
        var script = document.createElement('script');
        script.src = config.webRoot + 'js/echarts/echarts.min.js';
        script.onload = renderCharts;
        document.head.appendChild(script);
    }
    else
    {
        renderCharts();
    }

    function renderCharts()
    {
        /* 能力雷达。 */
        var radarDom = document.getElementById('annualRadar');
        if(radarDom)
        {
            var radar = echarts.init(radarDom);
            radar.setOption(
            {
                animation: false,
                tooltip: {},
                radar:
                {
                    radius: '65%',
                    indicator: annualRadarLabels.map(function(label){ return {name: label, max: annualRadarMax}; })
                },
                series: [{type: 'radar', data: [{value: annualRadarData, name: annualRadarLabels.join('/'), areaStyle: {opacity: 0.25}}]}]
            });
        }

        /* 月度操作趋势。 */
        var trendDom = document.getElementById('annualTrend');
        if(trendDom)
        {
            var trend = echarts.init(trendDom);
            trend.setOption(
            {
                animation: false,
                tooltip: {trigger: 'axis'},
                legend: {data: annualTrendNames, bottom: 0},
                grid: {left: 40, right: 16, top: 20, bottom: 40},
                xAxis: {type: 'category', data: annualMonths.map(function(m){ return m.substring(5); })},
                yAxis: {type: 'value', minInterval: 1},
                series: annualTrendData.map(function(data, index)
                {
                    return {name: annualTrendNames[index], type: 'bar', data: data, barMaxWidth: 16};
                })
            });
        }

        window.addEventListener('resize', function()
        {
            var radar2 = echarts.getInstanceByDom(document.getElementById('annualRadar'));
            if(radar2) radar2.resize();
            var trend2 = echarts.getInstanceByDom(document.getElementById('annualTrend'));
            if(trend2) trend2.resize();
        });
    }
});
