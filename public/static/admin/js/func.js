function getScreen() {
    var width = $(window).width();
    if (width > 1200) {
        return 3; //大屏幕
    } else if (width > 992) {
        return 2; //中屏幕
    } else if (width > 768) {
        return 1; //小屏幕
    } else {
        return 0; //超小屏幕
    }
}

function showImg(ts) {
    var obj = $(ts);
    var src = obj.attr('src');
    layer.photos({photos: {"data": [{"src": src}]}});
}

function _alert(msg) {
    var p1 = arguments[1] ? arguments[1] : {time: 1500};
    var p2 = arguments[2] ? arguments[2] : function () {
    };
    layer.msg(msg, p1, p2);
}

//ajax调用
function ajax(opt_p) {
    var opt_default = {
        type: 'post',
        url: '',
        dataType: 'json',
        data: {},
        xhrFields: {withCredentials: true},
        beforeSend: function () {
        },
        success: function () {
        },
        error: function () {
        },
        complete: function () {
        }
    };
    var $ = layui.jquery;
    var opt = $.extend(true, opt_default, opt_p);
    $.ajax(opt);
}

//文件上传
function fileUpload(new_opt) {
    var default_opt = {
        url: global.appurl + 'c=Index&a=upload', //上传接口
        elem: ''	//监听的元素
        //ext:'jpg|png|gif'	//允许的扩展类型
    }
    var opt = $.extend(default_opt, new_opt);
    layui.upload.render(opt);
}

function dataPage(opt) {
    var def = {
        elem: '#dataTable',
        method: 'post',
        where: {token: getToken()},
        toolbar: false,
        autoSort: false,
        cellMinWidth: 30, //全局定义常规单元格的最小宽度，layui 2.2.1 新增
        parseData: function (res) {
            if (res.code != 1) {
                if (res.code == '-98') {
                    _alert(res.msg, {}, function () {
                        location.href = 'admin';
                    });
                    return;
                } else {
                    _alert(res.msg);
                }
            }

            return res;
        },
        cols: null,
        done: function (res, curr, count) {
            //console.log(res);
        }
    }
    var $ = layui.jquery;
    var options = $.extend(true, def, opt);
    layui.table.render(options);
}