require(["jquery"],function($) {

    $(".block_leeloo_paid_courses #box-or-lines").click(function(e) {

        var gridsize = parseInt($(this).closest('.block_leeloo_paid_courses').find(".startgrid").attr("grid-size"), 10);

        e.preventDefault();
        $(this).toggleClass("grid");

        $(this).closest('.block_leeloo_paid_courses').find(".leeloo_paid_courses_list .coursebox").toggleClass('col-md-12');
        $(this).closest('.block_leeloo_paid_courses').find(".leeloo_paid_courses_list .coursebox").toggleClass('span12');
        $(this).closest('.block_leeloo_paid_courses').find(".leeloo_paid_courses_list .coursebox").toggleClass('list');

        $(this).closest('.block_leeloo_paid_courses').find(".leeloo_paid_courses_list .coursebox").toggleClass("col-md-" + gridsize);
        $(this).closest('.block_leeloo_paid_courses').find(".leeloo_paid_courses_list .coursebox").toggleClass("span" + gridsize);
        $(this).closest('.block_leeloo_paid_courses').find(".leeloo_paid_courses_list .coursebox").toggleClass('grid');

    });

    $('.leeloo_cert').on('click', function(e){
        e.preventDefault();
        var modal = $(this).attr('data-target');
        console.log(modal);
        $(modal+' .modal-body').html('<iframe class="leeloo_frame" src="'+$(this).attr('href')+'"></iframe>');
    });
    $('.leeloo_FC_Modal').on('hidden.bs.modal', function () {
        location.reload();
    });

});
