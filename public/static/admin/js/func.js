function getScreen() {
    var width = window.screen.width;
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
    var $ = layui.jquery;
    var obj = $(ts);
    var src = obj.attr('src');
    layui.layer.photos({photos: {"data": [{"src": src}]}});
}

function _alert(msg) {
    var p1 = arguments[1] ? arguments[1] : {time: 1500};
    var p2 = arguments[2] ? arguments[2] : function () {
    };
    layui.layer.msg(msg, p1, p2);
}

function alertWarning(msg, callback = null) {
    var p1 = callback ? callback : function () {};
    layui.layer.msg(msg, {icon: 0, time:1500}, p1);
}

function alertSuccess(msg, callback = null) {
    var p1 = callback ? callback : function () {};
    layui.layer.msg(msg, {icon: 1, time:1200}, p1);
}

function alertError(msg, callback = null) {
    var p1 = callback ? callback : function () {};
    layui.layer.msg(msg, {icon: 2, time:1500}, p1);
}

function jResult(code, msg, data = null) {
    return {code: code, msg: msg, data: data};
}

//ajax调用
function ajax(opt_p) {
    var opt_default = {
        type: 'post',
        url: '',
        dataType: 'json',
        data: {token: getAccessToken()},
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
        where: {token: getAccessToken()},
        toolbar: false,
        autoSort: false,
        cellMinWidth: 30, //全局定义常规单元格的最小宽度，layui 2.2.1 新增
        parseData: function (res) {
            if (res.code !== '0') {
                if (res.code === '-98') {
                    alertError(res.msg, function () {
                        location.href = 'admin';
                    });
                } else {
                    alertError(res.msg);
                }
                return;
            }
            var odata = {};
            for (var i in res.data) {
                if (i == 'list') {
                    continue;
                }
                odata[i] = res.data[i];
            }
            return {
                "code": res.code, //解析接口状态
                "msg": res.msg, //解析提示文本
                "count": res.data.count, //解析数据长度
                "data": res.data.list, //解析数据列表
                "odata": odata
            };
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

function laytplAfterAjax(ajax_opt, laytpl_opt) {
    ajax_opt.success = function (res) {
        console.log(res);

        if (res.code === '0') {
            var $ = layui.jquery;
            var data = res.data;
            laytpl_opt.type = 1;
            laytpl_opt.shadeClose = true;
            laytpl_opt.btn = ['确定', '取消'];
            laytpl_opt.content = layui.laytpl($(laytpl_opt.laytplId).html()).render({data: data}),
            layui.layer.open(laytpl_opt);
        } else {
            alertError(res.msg);
        }
    };
    ajax(ajax_opt);
}

