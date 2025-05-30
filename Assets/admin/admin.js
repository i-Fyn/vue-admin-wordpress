/****
 * 1.把现有的 window.onload 事件处理函数的值存入变量 oldonload;
 * 2.如果在这个处理函数上还没有绑定任何函数，就像平时那样把新函数添加给它；
 * 3.如果在这个处理函数上已经绑定了一些函数，就把新函数追加到现有指令的末尾。
 * */
function addLoadEvent(func) {
    var oldonload = window.onload;
    if(typeof window.onload != 'function'){
        window.onload = func;
    }else{
        window.onload = function(){
            oldonload();
            func();
        }
    }
}

(function ($, window, document, undefined) {
jQuery(document).ready(function($) {
    function ajax(data) {
        $.ajax({
             type: "POST",
             url: ajaxurl, // WordPress提供的ajax处理URL
             data: data, 
             dataType: "json", 
             error: function(err) {
                var html = '<div style="padding: 10px;margin: 0;" class="notice notice-error"><b>网络异常或者操作失败，请稍候再试！ ' + err.status + "|" + err.statusText + "</b></div>";
                
                if(err.responseText && err.responseText.indexOf("致命错误") > -1) {
                    html = '<div style="padding: 10px;margin: 0;" class="notice notice-error"><b>网站遇到致命错误，请检查插件冲突或通过错误日志排除错误</b></div>';
                }
                
                $(".ajax-notice").html(html);
             },
             success: function(res){
                $(".ajax-notice").html('<div style="padding: 10px;margin: 0;" class="notice notice-success"><b>'+ (res.msg || res.data) +'</b></div>');
                if(res.status || res.success) {
                    setTimeout(()=> {
                        location.reload()
                    }, 1000)
                }
            }
        });
    }
    
    $('#authorization_submit').click((e)=>{
        var data = {
            action:'admin_curl_aut',
            key:$('.regular-text').val()
        };
        return ajax(data)
    });
    
    //文件上传
    $(".islide-upload-file").change((event)=>{
        var formData = new FormData(); //创建FormData对象，将所需的信息封装到内部，以键值对的方式
        formData.append('file', event.target.files[0]) //参数封装格式,可以是文件，亦可以是普通的字符串, event.target.files[0].name
        
        formData.append('type', event.target.getAttribute('module_type'))

        $(".islide-upload").html('<i class="fa fa-spin fa-spinner"></i> 上传中...');

        $.ajax({
            type: 'post',
            url: ajaxurl + "?action=module_file_upload", //上传文件的请求路径必须是绝对路劲
            cache:false,//不需要缓存操作
            data:formData,//传递的参数就是FormData
            contentType:false,//由于提交的对象是FormData,所以要在这里更改上传参数的类型
            processData:false,//提交对象是FormData,不需要对数据做任何处理
            success:function (data) {
                $('.islide-upload-file').val('');//清空上传
                data = JSON.parse(data);
                if(data.status == 200) {
                    $(".ajax-notice").html('<div style="padding: 10px;margin: 0;" class="notice notice-success"><b>'+data.msg+'</b></div>');
                    
                    //刷新页面
                    setTimeout(()=> {
                        window.location.reload();
                    },1000);
                } else {
                    $(".ajax-notice").html('<div style="padding: 10px;margin: 0;" class="notice notice-error"><b>'+data.msg+'</b></div>');
                    $(".islide-upload").html('<i class="fa fa-cloud-upload"></i> 上传');
                }
            },
            error:function (err) {
                var html = '<div style="padding: 10px;margin: 0;" class="notice notice-error"><b>网络异常或者操作失败，请稍候再试！ ' + err.status + "|" + err.statusText + "</b></div>";
            
                if(err.responseText && err.responseText.indexOf("致命错误") > -1) {
                    html = '<div style="padding: 10px;margin: 0;" class="notice notice-error"><b>网站遇到致命错误，请检查插件冲突或通过错误日志排除错误</b></div>';
                }
                
                $(".ajax-notice").html(html);
                
                $('.islide-upload-file').val('');
                $(".islide-upload").html('<i class="fa fa-cloud-upload"></i> 上传');
            }
        })
    });
    
    // 备份
    // 监听父元素的点击事件，通过事件目标来判断点击的是哪个按钮
    $(document).on('click', '.action-delete, .action-restore', function() {
        var $button = $(this);
    
        if ($button.hasClass('processing')) {
            // 按钮正在处理中，不执行任何操作
            return;
        }
        
        var backupIndex = $(this).data('index');
        var action = $(this).hasClass('action-delete') ? 'delete' : 'restore';
        var value = $('.backup-item').eq(backupIndex).find('.item-left').text();
        var confirmationMessage = action === 'delete' ? '确定要删除'+ value +'吗？' : '确定要恢复'+ value +'吗？';
        
        // 弹出确认框
        if (confirm(confirmationMessage)) {
            // 禁用其他按钮
            $('.action-delete, .action-restore').not($button).prop('disabled', true);
            
            // 添加处理中样式，并禁用当前按钮
            $button.addClass('processing').prop('disabled', true).prepend('<i class="fa fa-spin fa-spinner"></i>');
            
            var data = {
                action: 'delete_restore_backup',
                backup_index: backupIndex,
                _action: action
            }
            return ajax(data);
        } else {
            // 取消操作，启用所有按钮
            $('.action-delete, .action-restore').prop('disabled', false);
        }
    });
    
    // 备份当前设置按钮的点击事件
    $('.backup-current-btn').click(function() {
        $(this).prepend('<i class="fa fa-spin fa-spinner"></i>');
        return ajax({ action: 'backup_current' })
    });
    
    // menu
    if($("#tr-grabber-menu").length) {
        $('.wrap .wp-header-end').after('<nav class="wp-filter" id="tr-grabber-menunav"></nav>');
        $("#tr-grabber-menu").appendTo("#tr-grabber-menunav");
        $("#tr-grabber-menu").css("display", "block");
        //$("#tr-grabber-menunav").next().remove();
    }
        
    //剧集编辑连接
    if($(".islide-post—parent—id").length) {
        $('.islide-post—parent—id input').each(function(index, element) {
            var postParentId = $(element).val();
            $(element).closest('.csf-cloneable-content').find('.islide-edit-episode a.button-primary').attr('href', function(index, value) {
                return value + '&post=' + postParentId;
            });
        });
    }
});

})(jQuery, window, document);

jQuery(".csf-header-left").click(function() {
    if (jQuery("#adminmenumain").css('display') == 'block') {
        jQuery("#wpcontent").css("margin-left", "0");
        jQuery("#adminmenumain").hide(0);
    } else {
        jQuery("#adminmenumain").show(0);
        jQuery("#wpcontent").css("margin-left", jQuery("#adminmenuwrap").width());
    }
});

//模块启用设置
function islide_module_option(type,callback,e) {
    jQuery.ajax({
        type: "POST",
        url: 'admin-ajax.php?action=islide_module_option',
        data: {
            type:type,
            callback:callback,
        }, 
        dataType: "json", 
        error: function(err) {
            var html = '<div style="padding: 10px;margin: 0;" class="notice notice-error"><b>网络异常或者操作失败，请稍候再试！ ' + err.status + "|" + err.statusText + "</b></div>";
            
            if(err.responseText && err.responseText.indexOf("致命错误") > -1) {
                html = '<div style="padding: 10px;margin: 0;" class="notice notice-error"><b>网站遇到致命错误，请检查插件冲突或通过错误日志排除错误</b></div>';
            }
            
            jQuery(".ajax-notice").html(html);
         },
        success: function(data){ 
            //data = JSON.parse(data);
            if(data.status == 200) {
                jQuery(".ajax-notice").html('<div style="padding: 10px;margin: 0;" class="notice notice-success"><b>'+data.msg+'</b></div>');
                    window.location.reload();
            } else {
                jQuery(".ajax-notice").html('<div style="padding: 10px;margin: 0;" class="notice notice-error"><b>'+data.msg+'</b></div>');
            }
        }
    });
}

function islideUploadFile(input, callback) {
    input = jQuery(input);
    
    if(!input.length) return;
    
    input.change((event)=>{
        var formData = new FormData();
        formData.append('file', event.target.files[0]);
        formData.append('post_id', document.getElementById('post_ID').value);
        
        var action = event.target.getAttribute('action');
        
        if(!action) return;

        input.siblings('.islide-upload').html('<i class="fa fa-spin fa-spinner"></i> 上传中...');

        jQuery.ajax({
            type: 'post',
            url: "admin-ajax.php?action="+action,
            cache:false,
            data:formData,
            contentType:false,
            processData:false,
            success:function (data) {
                input.val('');
                data = JSON.parse(data);
                if(data.status == 200) {
                    input.siblings(".ajax-notice").html('<div style="padding: 10px;margin: 0;" class="notice notice-success"><b>'+data.msg+'</b></div>');
                    callback(data);
                } else {
                    input.siblings(".ajax-notice").html('<div style="padding: 10px;margin: 0;" class="notice notice-error"><b>'+data.msg+'</b></div>');
                    input.siblings('.islide-upload').html('<i class="fa fa-cloud-upload"></i> 上传');
                }
            },
            error:function (err) {
                var html = '<div style="padding: 10px;margin: 0;" class="notice notice-error"><b>网络异常或者操作失败，请稍候再试！ ' + err.status + "|" + err.statusText + "</b></div>";

                if(err.responseText && err.responseText.indexOf("致命错误") > -1) {
                    html = '<div style="padding: 10px;margin: 0;" class="notice notice-error"><b>网站遇到致命错误，请检查插件冲突或通过错误日志排除错误</b></div>';
                }

                input.siblings(".ajax-notice").html(html);

                input.val('');
                input.siblings('.islide-upload').html('<i class="fa fa-cloud-upload"></i> 上传');
            }
        });
    });
}

// 然后在需要上传文件的地方调用这个函数即可，例如：

islideUploadFile(".upload-file", function(data) {
    //上传成功后的回调函数
    //刷新页面
    setTimeout(()=> {
        window.location.reload();
    },1000);
});

function orderEcharts(data, title) {
    var chart = echarts.init(document.getElementById('order-echarts'));
    var options = {
        title: {
            text: title,
        },
        tooltip: {
            trigger: "axis"
         },
        grid: {
            top: 70,
            right: 0,
            left: 10,
            bottom: 35,
            containLabel: true
        },
        xAxis: {
            type: "category",
            axisLabel: {
                fontSize: 12,
                formatter: function(t) {
                    if (!t)
                        return "";
                    var e = t.split("-");
                    e.shift();
                    return e.join("-");
                }
            },
            data: data.map((item) => {
                return item.date;
            }),
            boundaryGap: true //坐标轴两边留白
        },
        yAxis: {
            axisLabel: {
                margin: 10,
            }
        },
        series: [{
            name:"收入",
            type: "line",
            smooth: 0.6,
            lineStyle: {
                width: 3
            },
            label: {
                show: true,
                position: 'top'
            },
            areaStyle: {},
            data: data.map((item) => {
                return item.total;
            }),
        }],
    }
    
    chart.setOption(options);
    
    addLoadEvent(()=> {
        chart.resize();
    })
    // chart.resize();
    window.addEventListener("resize", ()=> {
        chart.resize();
    });

}

function showSevenDaysData() {
    orderEcharts(showOrderData.daily, '最近7天订单数据');
    orderEchartsPie(showIncomeData.seven_days_income, '最近7天');
}

if(typeof showOrderData != 'undefined' && typeof showIncomeData != 'undefined') {
    showSevenDaysData();
}

function showThirtyDaysData() {
    orderEcharts(showOrderData.monthly, '最近一个月订单数据');
    orderEchartsPie(showIncomeData.thirty_days_income, '最近一个月');
}

function showYearlyData() {
    orderEcharts(showOrderData.yearly, '最近一年订单数据');
    orderEchartsPie(showIncomeData.total_income, '最近一年');
}

function orderEchartsPie(data, title) {
    var chart = echarts.init(document.getElementById("order-echarts-Pie"));
    option = {
        title: [{
                text: title + '收入分析',
                left: 'center'
            },
        ],
        legend: {
            //orient: 'vertical',
            left: 'left',
            bottom: 20,
        },
        tooltip: {},
        series: [{
                type: 'pie',
                radius: [20, 140],
                      center: ['50%', '45%'],
                      roseType: 'area',
                      itemStyle: {
                        borderRadius: 8
                      },
                data:data,
                label: {
                    formatter: '{b} ({d}%)'
                }
            },
        ]
    };
    
    chart.setOption(option);
    
    addLoadEvent(()=> {
        chart.resize();
    })
    // chart.resize();
    window.addEventListener("resize", function() {
        chart.resize();
    });
}

// orderEchartsPie();
function dataEcharts() {
    var chart = echarts.init(document.getElementById("data-echarts"));
    var option = {
        title: {
            text: "最近七天数据",
            //show: false
        },
        tooltip: {
            trigger: "axis"
         },
        legend: {
            data: ["发文数", "评论数", "注册用户数", "今日签到"],
            //selectedMode:"single", //单选
            // selected: {
            //     "发文数": true, // 默认选中发文数量
            //     "评论数": false,
            //     "注册用户数": false,
            //     "签到数": false,
            // },
            bottom: 0,
            textStyle: {
                padding: [0, 0, 0, -4]
            },
            itemGap: 20,
            icon: "path://M2 0C0.895431 0 0 0.89543 0 2V12C0 13.1046 0.89543 14 2 14H12C13.1046 14 14 13.1046 14 12V2C14 0.895431 13.1046 0 12 0H2ZM9.34169 4.60912C9.53037 4.3858 9.86958 4.3713 10.0766 4.57771L10.4283 4.92832C10.6126 5.11202 10.6252 5.40635 10.4572 5.60511L7.27953 9.36617L7.30525 9.39302L6.99174 9.7068L6.71249 10.0373C6.63935 10.1239 6.54357 10.1791 6.44165 10.2022C6.25749 10.2773 6.03787 10.2378 5.8915 10.0849L4.04029 8.15181C3.85227 7.95547 3.85557 7.64489 4.04771 7.45258L4.40095 7.09905C4.59918 6.90065 4.92179 6.90408 5.11577 7.10664L6.23981 8.28043L9.34169 4.60912Z"
        },
        grid: {
            top: 70,
            right: 0,
            left: 10,
            bottom: 35,
            containLabel: true
        },
        xAxis: {
            type: "category",
            axisLabel: {
                fontSize: 12,
                formatter: function(t) {
                    if (!t)
                        return "";
                    var e = t.split("-");
                    e.shift();
                    return e.join("-");
                }
            },
            data: showData.dates,
            boundaryGap: true //坐标轴两边留白
        },
        yAxis: {
            //interval: 0,
            axisLabel: {
                margin: 10,
            }
        },
        series: [
            {
                name: "发文数",
                type: "line",
                smooth: 0.6,
                lineStyle: {
                    width: 3
                },
                data: showData.posts,
            },
            {
                name: "评论数",
                type: "line",
                smooth: 0.6,
                lineStyle: {
                    width: 3
                },
                data: showData.comments,
            },
            {
                name: "注册用户数",
                type: "line",
                smooth: 0.6,
                lineStyle: {
                    width: 3
                },
                data: showData.users,
            },
            {
                name: "签到数",
                type: "line",
                smooth: 0.6,
                lineStyle: {
                    width: 3
                },
                data:  showData.sign_ins,
            },
        ],
    };

    chart.setOption(option);
    
    addLoadEvent(()=> {
        chart.resize();
        document.querySelectorAll(".data-chart").forEach((e,i)=>{
            e.style.opacity = "1";
        })
    })
    
    // chart.resize();
    
    window.addEventListener("resize", function() {
        chart.resize();
    });
}

if( typeof showData != 'undefined') {
    dataEcharts()
    addLoadEvent(()=> {
        dataEcharts()
    })
}

function downloadTemplate(){
    
    if( typeof islidedownloadtemplate == 'undefined') return;
    
    const downloads = document.querySelectorAll('[data-controller="islide_single_post_download_open"] > .csf-cloneable-wrapper > .csf-cloneable-item');
    const temp = islidedownloadtemplate;

    for (let index = 0; index < downloads.length; index++) {

        jQuery(downloads[index]).find('[name="islide_single_post_download[islide_single_post_download_group]['+index+'][preset_template]"]').on('change', function() {
            let k = jQuery(this).find(':selected').val()
            //console.log(temp[k-1])

            let p = jQuery(downloads[index]);
            //资源名称
            p.find('[name="islide_single_post_download[islide_single_post_download_group]['+index+'][title]"]').val(temp[k-1].title)
            //缩略图
            p.find('[name="islide_single_post_download[islide_single_post_download_group]['+index+'][thumb]"]').val(temp[k-1].thumb)
            p.find('[name="islide_single_post_download[islide_single_post_download_group]['+index+'][thumb]"]').trigger('change')
            //下载权限
            p.find('[name="islide_single_post_download[islide_single_post_download_group]['+index+'][rights]"]').val(temp[k-1].rights)
            
            if(temp[k-1].download_group.length){
                for (var i = 0; i < temp[k-1].download_group.length; i++) {
                    p.find('.csf-cloneable-add').click()
                    p.find('[name="islide_single_post_download[islide_single_post_download_group]['+index+'][download_group]['+i+'][name]"]').val(temp[k-1].download_group[i].name)
                    p.find('[name="islide_single_post_download[islide_single_post_download_group]['+index+'][download_group]['+i+'][url]"]').val(temp[k-1].download_group[i].url)
                    p.find('[name="islide_single_post_download[islide_single_post_download_group]['+index+'][download_group]['+i+'][tq]"]').val(temp[k-1].download_group[i].tq)
                    p.find('[name="islide_single_post_download[islide_single_post_download_group]['+index+'][download_group]['+i+'][jy]"]').val(temp[k-1].download_group[i].jy)
                }
            }
            
            p.find('[name="islide_single_post_download[islide_single_post_download_group]['+index+'][attrs]"]').val(temp[k-1].attrs)
            p.find('[name="islide_single_post_download[islide_single_post_download_group]['+index+'][demo]"]').val(temp[k-1].demo)
        })

    }
}

addLoadEvent(downloadTemplate)

// function changeRadioChange(){
//     jQuery('[data-depend-id="islide_single_post_download_open"]').on( 'change',function() {
//         console.log(11111)
//         setTimeout(function(){
//             downloadTemplate()
//         },500)
//     })
// }

// addLoadEvent(changeRadioChange)

function downupaction(){
    
    jQuery('[data-controller="islide_single_post_download_open"] > .csf-cloneable-add').on( 'click',function() {
        setTimeout(function(){
            downloadTemplate()
        },500)
    })
}

addLoadEvent(downupaction)

