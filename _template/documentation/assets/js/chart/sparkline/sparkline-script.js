(function ($) {
  "use strict";
  var sparkline_chart = {
    init: function () {
      setTimeout(function () {
        $("#simple-line-chart-sparkline").sparkline([5, 10, 20, 14, 17, 21, 20, 10, 4, 13, 0, 10, 30, 40, 10, 15, 20], {
          type: "line",
          width: "100%",
          height: "150",
          tooltipClassname: "chart-sparkline",
          lineColor: "#122635",
          fillColor: "transparent",
          highlightLineColor: "#122635",
          highlightSpotColor: "#122635",
          targetColor: "#122635",
          performanceColor: "#122635",
          boxFillColor: "#122635",
          medianColor: "#122635",
          minSpotColor: "#122635",
        });
      });
    },
  };
  sparkline_chart.init();
})(jQuery);
