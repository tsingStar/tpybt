<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/26
 * Time: 16:04
 */
$leftmenu = [
    [
        'navName'=>'店铺信息',
        'navChild'=>[
            [
                'navName'=>'基本信息',
                'url'=>'ShopInfo/baseInfo'
            ],
            [
                'navName'=>'店员管理',
                'url'=>'ShopInfo/employee'
            ],
            [
                'navName'=>'营业时间设置',
                'url'=>'ShopInfo/openTime'
            ],
            [
                'navName'=>'配送时间设置',
                'url'=>'ShopInfo/dispatchTime'
            ],
            [
                'navName'=>'配送小区管理',
                'url'=>'ShopInfo/dispatchArea'
            ],
            [
                'navName'=>'推荐搜索关键字',
                'url'=>'ShopInfo/keywords'
            ],
            [
                'navName'=>'店铺公告',
                'url'=>'ShopInfo/reportList'
            ],
            [
                'navName'=>'轮播图设置',
                'url'=>'ShopInfo/swiperList'
            ],
        ]
    ],
    [
        'navName'=>'商品管理',
        'navChild'=>[
            [
                'navName'=>'分类管理',
                'url'=>'Products/cateIndex'
            ],
            [
                'navName'=>'商品库',
                'url'=>'Products/GoodsIndex'
            ]
        ]
    ],
    [
        'navName'=>'特殊商品管理',
        'navChild'=>[
            [
                'navName'=>'新鲜水果管理',
                'url'=>'Products/bulk_fruit'
            ],
            [
                'navName'=>'新鲜蔬菜管理',
                'url'=>'Products/bulk_other'
            ],
            [
                'navName'=>'整箱、套装商品管理',
                'url'=>'Products/combine_goods'
            ],
            [
                'navName'=>'限时抢购',
                'url'=>'Products/sec_active'
            ],
            [
                'navName'=>'积分兑换商品',
                'url'=>'Products/score_goods'
            ],
        ]
    ],
    [
        'navName'=>'订单管理',
        'navChild'=>[
            [
                'navName'=>'全部订单',
                'url'=>'Order/index'
            ],
            [
                'navName'=>'待配送订单',
                'url'=>'Order/dispatchList'
            ],
            [
                'navName'=>'退款申请订单',
                'url'=>'Order/refundList'
            ],
            [
                'navName'=>'积分兑换订单',
                'url'=>'Order/scoreList'
            ],
        ]
    ],
//    [
//        'navName'=>'活动管理',
//        'navChild'=>[
//            [
//                'navName'=>'活动列表',
//                'url'=>''
//            ],
//            [
//                'navName'=>'添加活动',
//                'url'=>''
//            ],
//        ]
//    ],
//    [
//        'navName'=>'账单管理',
//        'navChild'=>[
//            [
//                'navName'=>'日销售统计',
//                'url'=>''
//            ],
//            [
//                'navName'=>'申请提现',
//                'url'=>''
//            ],
//            [
//                'navName'=>'提现记录',
//                'url'=>''
//            ],
//        ]
//    ],
//    [
//        'navName'=>'监控设备管理',
//        'navChild'=>[
//            [
//                'navName'=>'日销售统计',
//                'url'=>''
//            ],
//        ]
//    ],
];