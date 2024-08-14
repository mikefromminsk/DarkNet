function controller(callback) {
    let app = angular.module("App", ['ngMaterial', 'ngAnimate'])
    app.config(function ($mdThemingProvider) {
        $mdThemingProvider.theme('default')
            .primaryPalette('indigo')
            .accentPalette('grey');
        // add light theme
        $mdThemingProvider.theme('default')
            .dark();
    })

    /* You supplied an invalid color palette for theme default's primary palette. Available palettes: red, pink, purple, deep-purple, ' +
     'indigo, blue, light-blue, cyan, teal, green, light-green, lime, yellow, amber, orange, deep-orange, brown, grey, blue-grey*/
    app.controller("Controller", callback)
}

function selectFile(success, accept) {
    var input = document.createElement('input')
    input.type = 'file'
    input.accept = accept || "*/*"
    input.onchange = e => {
        if (success != null)
            success(e.target.files[0])
    }
    input.click()
}

function objectToForm(data) {
    var formData = new FormData();
    angular.forEach(data, function (value, key) {
        formData.append(key, value);
    });
    return formData;
}

function downloadFile(uri) {
    var link = document.createElement("a");
    link.setAttribute('download', uri.split(/(\\|\/)/g).pop());
    link.href = uri;
    document.body.appendChild(link);
    link.click();
    link.remove();
}

function animateFocus(id) {
    document.getElementById(id).animate(
        [
            {transform: "translateY(0px)"},
            {transform: "translateY(1px)"},
            {transform: "translateY(-1px)"},
            {transform: "translateY(0px)"},
        ],
        {
            duration: 300,
            iterations: 5,
        },
    )
}

function setFocus(id) {
    setTimeout(function () {
        document.getElementById(id).focus()
    }, 500)
}

function showError(message, error) {
    if (error) {
        error(message)
    } else {
        if (window.$mdToast != null) {
            window.$mdToast.show(window.$mdToast.simple().textContent(message))
        } else {
            alert(message)
        }
    }
}

function showSuccess(message, success) {
    if (window.$mdToast != null) {
        window.$mdToast.show(window.$mdToast.simple().textContent(message))
    } else {
        alert(message)
    }
    if (success)
        success(message)
}


function getChartOptions() {
    return {
        layout: {
            background: {color: '#222'},
            textColor: '#DDD',
        },
        grid: {
            vertLines: {color: '#444'},
            horzLines: {color: '#444'},
        },
        crosshair: {
            mode: LightweightCharts.CrosshairMode.Normal,
        },
    }
}

function chartResize(tradeChart, chart) {
    new ResizeObserver(entries => {
        if (entries.length === 0 || entries[0].target !== tradeChart) return;
        const newRect = entries[0].contentRect;
        chart.applyOptions({height: newRect.height, width: newRect.width});
    }).observe(tradeChart)
}

function createChart(id) {
    var tradeChart = document.getElementById(id)
    var chart = LightweightCharts.createChart(tradeChart, getChartOptions())
    chartResize(tradeChart, chart)
    return chart;
}