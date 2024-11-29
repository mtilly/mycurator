jQuery(document).ready(function($) {
    $("figure.quick-start-video img").click(function () {
        //Quick Start Video
        //var html_str = $(this).parent().html();
        //alert(html_str);
        var iframe_str = '<iframe src="//www.youtube.com/embed/rlPwJY5ZI4M?autoplay=1&rel=0" width="600" height="300" frameborder="0" allowfullscreen="allowfullscreen"></iframe>'
        $(this).parent().html(iframe_str);
   });
   $("figure.advanced-features-video img").click(function () {
        //Advanced Features Video
        //var html_str = $(this).parent().html();
        //alert(html_str);
        var iframe_str = '<iframe src="//www.youtube.com/embed/pG2SA4Hbg3I?autoplay=1&rel=0" width="600" height="300" frameborder="0" allowfullscreen="allowfullscreen"></iframe>'
        $(this).parent().html(iframe_str);
   });
   $("figure.notebooks-video img").click(function () {
        //Notebooks Video
        //var html_str = $(this).parent().html();
        //alert(html_str);
        var iframe_str = '<iframe src="//www.youtube.com/embed/jxHqDGhv-2Q?autoplay=1&rel=0" width="600" height="300" frameborder="0" allowfullscreen="allowfullscreen"></iframe>'
        $(this).parent().html(iframe_str);
   });
   $("figure.webinar-video img").click(function () {
        //Webinar Video
        //var html_str = $(this).parent().html();
        //alert(html_str);
        var iframe_str = '<iframe src="//www.youtube.com/embed/XgHc7EiqZ0s?autoplay=1&rel=0" width="600" height="300" frameborder="0" allowfullscreen="allowfullscreen"></iframe>'
        $(this).parent().html(iframe_str);
   });
   
});
