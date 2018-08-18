<?php
/**
 * 商品管理控制器
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018.5.3
 * Time: 08:34
 */

namespace app\shop\controller;


use app\common\model\GoodsProp;
use app\common\model\Shop;
use app\common\model\ShopCate;
use app\common\model\Goods;
use app\common\model\SixunOpera;

class Products extends ShopBase
{
    protected function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
    }

    /**
     * 分类管理
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function cateIndex()
    {
        $shopCateModel = new ShopCate();
        $cateArr = $shopCateModel->getCate(SHOP_ID, 0);
        $this->assign('cateList', $cateArr);
        return $this->fetch();
    }

    /**
     * 设置首页推荐分类
     */
    public function setCateRecommend()
    {
        $cate_id = input('post.cate_id');
        $recommend = input('post.recommend');
        $shopCate = new ShopCate();
        $res = $shopCate->setRecommend($cate_id, $recommend);
        if ($res) {
            exit_json();
        } else {
            exit_json(-1, '设置失败');
        }
    }

    /**
     * 查看子分类
     */
    public function cateDet()
    {
        $parent_id = input('parent_id');
        $shopCate = new ShopCate();
        $cateList = $shopCate->getCate(SHOP_ID, $parent_id);
        $this->assign('cateList', $cateList);
        return $this->fetch();
    }

    /**
     * 删除分类
     */
    public function delCate()
    {
        $ids = input('post.ids');
        $shopCate = new ShopCate();
        $res = $shopCate->delCate($ids);
        if ($res) {
            exit_json();
        } else {
            exit_json(-1);
        }
    }

    /**
     * 同步选取店铺分类
     */
    public function asyncCate()
    {
        $selectedIds = model('shopCate')->where(['shop_id' => SHOP_ID])->column('library_id');
        $libCates = model('goodslibrarycate')->where('is_async', 'eq', 0)->select();
        $libCate = getTree($libCates, 0, 'pid');
        $this->assign('selectedIds', $selectedIds);
        $this->assign('libCate', $libCate);
        return $this->fetch();
    }

    /**
     * 保存分类
     */
    public function saveCate()
    {
        $cateIds = input('post.idstr');
        $selected_cate = model('goodslibrarycate')->where('id', 'in', $cateIds)->select();
        $selected = getTree($selected_cate, 0, 'pid');
        foreach ($selected as $item) {
            $shopCate = new ShopCate();
            $parent_id = $shopCate->saveCate($item);
            foreach ($item['children'] as $v) {
                $shopCate1 = new ShopCate();
                $shopCate1->saveChildCate($v, $parent_id);
            }
        }
        exit_json();
    }

    /**
     * 商品列表
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function GoodsIndex()
    {
        $cateModel = new ShopCate();
        $cateList = $cateModel->field('id,name,parent_id pId')->where('shop_id', SHOP_ID)->select();
        $this->assign('cateList', $cateList);
        return $this->fetch();
    }

    /**
     * 根据分类获取商品
     */
    public function plist()
    {
        $cateId = input('cateId');
        $extra = [];
        if (input('name')) {
            $extra['name'] = ['like', "%" . input('name') . "%"];
        }
        if (input('gno')) {
            $extra['gno'] = input('gno');
        }
        if ($cateId) {
            $extra['cate_id'] = $cateId;
        }
        $where = array_merge(['shop_id' => SHOP_ID], $extra);
        $goodsList = model('goods')->where($where)->order('good_order desc')->select();
        $cat_list = model('shop_cate')->where('shop_id', SHOP_ID)->column('name', 'id');
//        $active_list = db('active')->where('is_open', 1)->column('active_name', 'id');
//        $active_list[0] = '普通商品';
        $active_list = config('active');
        $this->assign('active_list', $active_list);
        $this->assign('cat_list', $cat_list);
        $this->assign('goodsList', $goodsList);
        $this->assign('is_show', false);
        return $this->fetch();
    }

    /**
     * 同步商品信息
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function asyncGoods()
    {
        set_time_limit(0);
        $shopModel = new Shop();
        $shop = $shopModel->where('id', 'eq', SHOP_ID)->find();
        if (!$shop['fendian']) {
            exit_json(-1, '抱歉。该店铺下暂时没有实体店');
        } else {
            $fendian = $shop['fendian'];
            $sixun = new SixunOpera();
            if ($sixun->checkShopExist($fendian)) {
                $res = $sixun->asyncGoodsToShop($fendian, SHOP_ID);
                if ($res) {
                    exit_json();
                } else {
                    exit_json(-1, $sixun->getError());
                }
            } else {
                exit_json(-1, '实体店信息不存在，请确认后再同步');
            }
        }
    }

    /**
     * 手动添加商品
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function productAdd()
    {
        if (request()->isAjax()) {
            $data = [];
            $data['cate_id'] = input('post.cateId');
            $data['name'] = input('post.name');
            $data['gno'] = input('post.gno');
            $data['sale_price'] = input('post.cost');
            $data['active_price'] = input('post.active_price') > 0 ? input('post.active_price') : input('post.cost');
            $data['goodattr'] = input('post.goodattr');
            $data['have_det'] = input('have_det');
            $data['combine_sta'] = input('post.combine_sta');
            $data['img'] = join(',', $_POST['image']);
            $data['instro'] = input('post.instro');
            $data['shop_id'] = SHOP_ID;
            if ($data['have_det'] == 1) {
                $guigeArr = explode("@", input('post.guige'));
                $propArr = [];
                foreach ($guigeArr as $value) {
                    $propDet = explode(':', $value);
                    $tmpArr = [];
                    //暂存商品id为0，存入商品后统一修改
                    $tmpArr['good_id'] = 0;
                    $tmpArr['prop_name'] = $propDet[0];
                    $tmpArr['prop_price'] = $propDet[1] ? $propDet[1] : 0;
                    $tmpArr['prop_active_price'] = $propDet[2] ? $propDet[2] : 0;
                    $tmpArr['num'] = $propDet[3] ? $propDet[3] : 0;
                    $propArr[] = $tmpArr;
                }
            } else {
                $data['guige'] = input('guige');
            }
            $productModel = new Goods();
            $res = $productModel->allowField(true)->save($data);
            if ($res) {
                $good_id = $productModel->getLastInsID();
                if (isset($propArr)) {
                    $propData = [];
                    foreach ($propArr as $item) {
                        $t = $item;
                        $t['good_id'] = $good_id;
                        $propData[] = $t;
                    }
                    db('goods_prop')->insertAll($propData);
                }
                exit_json();
            } else {
                exit_json(-1, '添加失败');
            }
        }
        $cateModel = new ShopCate();
        $list = $cateModel->field('id, name, parent_id')->select();
        $cateTree = getTree($list, 0);
        $this->assign('cateTree', $cateTree);
        return $this->fetch();
    }

    /**
     * 更改商品状态
     */
    public function changeStatus()
    {
        $good_id = input('good_id');
        $code = input('code');
        $good = model('goods')->where('id', $good_id)->find();
        if($good['bulk_package'] == 1){
            $num = model('goods_prop')->where('good_id', $good_id)->where('num', 'gt', 0)->count();
            if($num<1){
                exit_json(-1, '称重商品无规格不可以上架');
            }
        }else{
            if($good['count']<=0){
                exit_json(-1,'商品库存不足，禁止上架');
            }
        }
        $res = model('goods')->save(['is_live' => $code], ['id' => $good_id]);
        if ($res) {
            exit_json();
        } else {
            exit_json(-1, '更改失败');
        }
    }

    /**
     * 更改首页推荐
     */
    public function set_recommend()
    {
        $good_id = input('good_id');
        $code = input('code');
        $res = model('goods')->save(['is_recommend' => $code], ['id' => $good_id]);
        if ($res) {
            exit_json();
        } else {
            exit_json(-1, '更改失败');
        }
    }

    /**
     * 更新商品图片
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function updateImage()
    {
        set_time_limit(0);
        $goods = model('goods')->where('shop_id', SHOP_ID)->select();
        foreach ($goods as $good) {
            //如果没有图片添加图片
            $data = array();
            $dir_top = showdir($good['gno'], 'ontop');
            if (count($dir_top) > 0) {
                $data['img'] = join(',', $dir_top);
            } else {
                $data['img'] = '';
            }
            //产品详情部分，append
            $dir_d = showdir($good['gno'], 'd');
            if (count($dir_d) > 0) {
                $data['instro'] = '';
                foreach ($dir_d as $k => $v) {
                    $data['instro'] .= '<img style="width:100%;height:auto;" src="' . $v . '" />';
                }
            }
            model('goods')->save($data, ['shop_id' => SHOP_ID, 'gno' => $good['gno']]);
        }
        exit_json();
    }

    /**
     * 编辑商品
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function productEdit()
    {
        $productModel = new Goods();
        if (request()->isAjax()) {
            $data = input('post.');
            if ($data['active_price'] == 0) {
                $data['active_price'] = $data['sale_price'];
            }
            if (isset($data['img'])) {
                $data['img'] = join(',', $data['img']);
            } else {
                $data['img'] = '';
            }
            $p = [];
            if (isset($data['prop'])) {
                $prop = $data['prop'];
                foreach ($prop as $k => $pr) {
                    foreach ($pr as $k1 => $v1) {
                        $p[$k1][$k] = $v1;
                    }
                }
                unset($data['prop']);
            } else {
                db('goods_prop')->where('good_id', $data['id'])->delete();
            }
            $res = $productModel->allowField(true)->save($data, ['id' => $data['id']]);

            if ($res) {
                //修改规格
                $prop_ids = [];
                foreach ($p as $item) {
                    if (!$item['prop_id']) {
                        $prop_data = $item;
                        unset($prop_data['prop_id']);
                        $prop_data['good_id'] = $data['id'];
                        model('goods_prop')->save($prop_data);
                        $prop_id = model('goods_prop')->getLastInsID();
                    } else {
                        $prop_data = $item;
                        $prop_id = $prop_data['prop_id'];
                        unset($prop_data['prop_id']);
                        db('goods_prop')->where('id', $item['prop_id'])->update($prop_data);
                    }
                    $prop_ids[] = $prop_id;
                }
                model('goods_prop')->whereNotIn('id', $prop_ids)->setField('num', 0);
                model('shopcart')->where('good_id', $data['id'])->whereNotIn('prop_id', $prop_ids)->where('prop_id', 'neq', 0)->delete();
                exit_json();
            } else {
                exit_json(-1, "保存失败");
            }
        }
        $product = $productModel->where('id', input('good_id'))->find();
        $cateModel = new ShopCate();
        $list = $cateModel->field('id, name, parent_id')->select();
        $cateTree = getTree($list, 0, 'parent_id');
        $pid = $cateModel->where('id', $product['cate_id'])->value('parent_id');
        $prop = db('goods_prop')->where(['good_id' => $product['id'], 'num' => ['gt', 0]])->select();
        $active_list = db('active')->where('is_open', 1)->column('active_name', 'id');
        $this->assign('active_list', $active_list);
        $this->assign('prop', $prop);
        $this->assign('pid', $pid);
        if (count($prop)) {
            //重置商品规格属性
            $product['have_det'] = 1;
        }
        $this->assign('product', $product);
        $this->assign('cateTree', $cateTree);
        return $this->fetch();
    }

    /**
     * 添加商品图片
     */
    public function addProductImg()
    {
        $file = request()->file('file');
        if ($file) {
            $info = $file->move(__UPLOAD__ . '/goodsimg/user', md5(microtime() . rand(1000, 9999)));
            if ($info) {
                $saveName = $info->getSaveName();
                $path = "/upload/goodsimg/user/" . $saveName;
                exit_json(1, '操作成功', $path);
            } else {
                // 上传失败获取错误信息
                exit_json(-1, $file->getError());
            }
        }
        exit_json();

    }

    /**
     * 根据id删除数据
     */
    function delData()
    {
        $ids = input('idstr');
        $goodModel = new Goods();
        $res = $goodModel->where('id', 'in', $ids)->delete();
        if ($res) {
            exit_json();
        } else {
            exit_json('操作失败');
        }
    }

    /**
     * 称重商品
     */
    public function bulk_goods()
    {
        $extra = [];
        if (input('name')) {
            $extra['name'] = ['like', "%" . input('name') . "%"];
        }
        if (input('gno')) {
            $extra['gno'] = input('gno');
        }
        $where = array_merge(['shop_id' => SHOP_ID, 'bulk_package' => 1], $extra);
        $goods_list = model('goods')->where($where)->order('good_order desc')->select();
        $cat_list = model('shop_cate')->where('shop_id', SHOP_ID)->column('name', 'id');

//        $active_list = db('active')->where('is_open', 1)->column('active_name', 'id');
//        $active_list[0] = '普通商品';
        $active_list = config('active');
        $this->assign('active_list', $active_list);
        $this->assign('cat_list', $cat_list);
        $this->assign('goodsList', $goods_list);
        $this->assign('is_bulk', 1);
        return $this->fetch('plist');
    }

    /**
     * 新鲜水果
     */
    public function bulk_fruit()
    {
        $extra = [];
        if (input('name')) {
            $extra['name'] = ['like', "%" . input('name') . "%"];
        }
        if (input('gno')) {
            $extra['gno'] = input('gno');
        }
        $cat_list = model('shop_cate')->where('shop_id', SHOP_ID)->column('name', 'id');
        foreach ($cat_list as $key => $item) {
            if ($item == '新鲜水果') {
                $cate_id = $key;
            }
        }
        if (isset($cate_id)) {
            $extra['cate_id'] = $cate_id;
        }
        $where = array_merge(['shop_id' => SHOP_ID, 'bulk_package' => 1], $extra);
        $goods_list = model('goods')->where($where)->order('good_order desc')->select();
        $active_list = config('active');
        $this->assign('active_list', $active_list);
        $this->assign('cat_list', $cat_list);
        $this->assign('goodsList', $goods_list);
        $this->assign('is_bulk', 1);
        return $this->fetch('plist');

    }

    /**
     * 新鲜蔬菜
     */
    public function bulk_other()
    {

        $extra = [];
        if (input('name')) {
            $extra['name'] = ['like', "%" . input('name') . "%"];
        }
        if (input('gno')) {
            $extra['gno'] = input('gno');
        }
        $cat_list = model('shop_cate')->where('shop_id', SHOP_ID)->column('name', 'id');
        foreach ($cat_list as $key => $item) {
            if ($item == '新鲜水果') {
                $cate_id = $key;
            }
        }
        if (isset($cate_id)) {
            $extra['cate_id'] = ['neq', $cate_id];
        }
        $where = array_merge(['shop_id' => SHOP_ID, 'bulk_package' => 1], $extra);
        $goods_list = model('goods')->where($where)->order('good_order desc')->select();
        $active_list = config('active');
        $this->assign('active_list', $active_list);
        $this->assign('cat_list', $cat_list);
        $this->assign('goodsList', $goods_list);
        $this->assign('is_bulk', 1);
        return $this->fetch('plist');

    }

    /**
     * 设置商品排序
     */
    public function setOrder()
    {
        $good_id = input('id');
        $num = input('num');
        $data['good_order'] = $num;
        $res = model('goods')->where('id', $good_id)->find()->save($data);
        if ($res) {
            exit_json();
        } else {
            exit_json(-1, '保存失败');
        }

    }


    /**
     * 称重商品
     */
    public function combine_goods()
    {
        $extra = [];
        if (input('name')) {
            $extra['name'] = ['like', "%" . input('name') . "%"];
        }
        if (input('gno')) {
            $extra['gno'] = input('gno');
        }
        $where = array_merge(['shop_id' => SHOP_ID, 'combine_sta' => 1], $extra);
        $goods_list = model('goods')->where($where)->order('good_order desc')->select();
        $cat_list = model('shop_cate')->where('shop_id', SHOP_ID)->column('name', 'id');
//        $active_list = db('active')->where('is_open', 1)->column('active_name', 'id');
//        $active_list[0] = '普通商品';
        $active_list = config('active');
        $this->assign('active_list', $active_list);
        $this->assign('cat_list', $cat_list);
        $this->assign('goodsList', $goods_list);
        $this->assign('is_com', 1);
        return $this->fetch('plist');
    }

    public function set_combine_num()
    {
        $good_id = input('id');
        $num = input('num');
        if ($num < 0) {
            exit_json(-1, '库存数量只能为数字');
        } else {
            $data['count'] = $num;
            if ($num == 0) {
                $data['is_live'] = 0;
            } else {
                $data['is_live'] = 1;
            }
            $res = model('goods')->where('id', $good_id)->find()->save($data);
            if ($res) {
                exit_json();
            } else {
                exit_json(-1, '保存失败');
            }
        }

    }

    /**
     * 批量下架整箱商品
     */
    public function downGoods()
    {
        $res = model('goods')->save(['count' => 0, 'is_live' => 0], ['shop_id' => SHOP_ID, 'combine_sta' => 1]);
        if ($res) {
            exit_json();
        } else {
            exit_json(-1, '操作失败');
        }
    }

    /**
     * 整箱扫码上架
     */
    public function scanLoad()
    {
        $gnos = input('props');
        $gno_arr = explode("\n", $gnos);
        $data = array_count_values($gno_arr);
        $count = 0;
        $fail = '';
        foreach ($data as $key => $val) {
//            $res = model('goods')->save(['count' => 'count+'.$val, 'is_live'=>1], ['gno' => $key, 'shop_id' => SHOP_ID]);
            $good = model('goods')->where(['gno' => $key, 'shop_id' => SHOP_ID])->find();
            if (!$good) {
                $fail .= '货号' . $key . '商品不存在。';
                continue;
            }
            $res = $good->save(['count' => $good['count'] + $val, 'is_live' => 1]);
            if ($res) {
                $count += $val;
            } else {
                $fail .= '货号' . $key . '商品上架失败';
            }
        }
        exit_json(1, "成功上架" . $count . "件商品，" . $fail);
    }

    /**
     * 活动商品
     * 方法废弃
     */
    public function active_goods()
    {
        $extra = [];
        if (input('name')) {
            $extra['name'] = ['like', "%" . input('name') . "%"];
        }
        if (input('gno')) {
            $extra['gno'] = input('gno');
        }
        $where = array_merge(['shop_id' => SHOP_ID, 'active_id' => ['neq', 0]], $extra);
        $goods_list = model('goods')->where($where)->select();
        $cat_list = model('shop_cate')->where('shop_id', SHOP_ID)->column('name', 'id');
        $active_list = db('active')->where('is_open', 1)->column('active_name', 'id');
        $active_list[0] = '普通商品';
        $this->assign('active_list', $active_list);
        $this->assign('cat_list', $cat_list);
        $this->assign('goodsList', $goods_list);
        return $this->fetch('plist');

    }

    /**
     * 限时抢购
     */
    public function sec_active()
    {
        $list = db('sec_active')->alias('a')->join('goods b', 'a.good_id=b.id')->field('a.*, b.name')->select();
        $this->assign('list', $list);
        return $this->fetch();
    }

    /**
     * 商品活动类型选择
     */
    public function active()
    {
        $good_id = input('good_id');
//        $list = db('active')->where('is_open', 1)->column('active_name', 'id');
//        $list[0] = "普通商品";
//        ksort($list);
        $list = config('active');
        $good = model('goods')->where('id', $good_id)->find();
        $this->assign('list', $list);
        $this->assign('good_id', $good_id);
        $this->assign('active_id', $good['active_id']);
        return $this->fetch();
    }

    /**
     * 设置活动类型
     */
    public function setActive()
    {
        $good_id = input('good_id');
        $active_id = input('active_id');
        $good = model('goods')->where('id', $good_id)->find();
        $res = $good->save(['active_id' => $active_id]);
//        var_dump($res);
//        exit;
        $active = config('active');
        array_shift($active);
        if ($active_id == '0') {
            foreach ($active as $key => $value) {
                db("$key")->where('good_id', $good_id)->delete();
            }
        } else {
            $r = db("$active_id")->where('good_id', $good_id)->find();
            if ($r) {
                unset($active[$active_id]);
                foreach ($active as $key => $value) {
                    db("$key")->where('good_id', $good_id)->delete();
                }
            } else {
                db("$active_id")->insert(['good_id' => $good_id, 'shop_id' => $good['shop_id'], 'gno' => $good['gno']]);
            }
        }
        if ($res) {
            exit_json();
        } else {
            exit_json(-1, '操作失败');
        }
    }

    /**
     * 设置排序
     */
    public function set_order()
    {
        $cate_id = input('cate_id');
        $ord_id = input('ord_id');
        $res = model('shop_cate')->save(['ord_id' => $ord_id], ['id' => $cate_id]);
        if ($res) {
            exit_json();
        } else {
            exit_json(-1, '操作失败');
        }

    }

    /**
     * 抢购活动编辑
     */
    public function sec_active_edit()
    {
        if (request()->isAjax()) {
            $data = input('post.');
            $ac = db('sec_active')->where($data)->find();
            if (!$ac) {
                $ac = db('sec_active')->where('id', $data['id'])->update([
                    'start_time' => $data['start_time'],
                    'end_time' => $data['end_time']
                ]);
            }
            if ($ac) {
                exit_json();
            } else {
                exit_json(-1, '保存失败');
            }
        }
        $active_id = input('active_id');
        $active = db('sec_active')->alias('a')->join('goods b', 'a.good_id=b.id')->field('a.*, b.name')->where('a.id', $active_id)->find();
        $this->assign('item', $active);
        return $this->fetch();
    }

    /**
     * 开启活动
     */
    public function changeActive()
    {
        $active_id = input('active_id');
        $act = db('sec_active')->where('id', $active_id)->find();
        $status = input('status') == 0 ? 1 : 0;
        if (!$act['start_time'] || !$act['end_time']) {
            exit_json(0);
        } else {
            $res = db('sec_active')->where('id', $active_id)->update(['status' => $status]);
            if ($res) {
                exit_json();
            } else {
                exit_json(-1);
            }
        }
    }

    /**
     * 删除活动
     */
    public function delSecActive()
    {
        $active_id = input('idstr');
        if (!$active_id) {
            exit_json(-1, '条目不存在');
        } else {

            $res = db('sec_active')->where('id', 'in', $active_id)->delete();
            if ($res) {
                exit_json();
            } else {
                exit_json(-1, '操作失败');
            }
        }

    }

    /**
     * 添加产品规格
     */
    function addProps()
    {
        $props = input('props');
        $propArr = explode("\n", $props);
        $count = 0;
        $flag = true;
        $error_str = "";
        foreach ($propArr as $p) {
            $valid = substr($p, 0, 2);
            if ($valid != 22) {
                $error_str .= $p.'、';
                continue;
            }
            $gno = substr($p, 2, 5);
            if ($gno == '') {
                $error_str .= $p.'、';
                continue;
            }
            $price = (double)substr($p, -6);
            $good = model('goods')->where('gno', $gno)->where('shop_id', SHOP_ID)->find();
            if (!$good) {
                $error_str .= $p.'、';
                continue;
            }
            if ($good['bcost'] <= 0) {
                $error_str .= $p.'、';
                continue;
            }
            $weight = round($price / $good['bcost']);
            $price = floor($price / 10);
            $price = sprintf('%.2f', $price / 100);
            $discount = model('shop')->where('id', SHOP_ID)->value('discount');
            $active_price = $price * $discount;
            $good_props = new GoodsProp();
            $res = $good_props->isUpdate(false)->save(['good_id' => $good['id'], 'prop_name' => $weight . 'g', 'prop_price' => $price, 'num' => 1, 'prop_no' => $p, 'prop_active_price' => $active_price]);
            if ($res) {
//                model('goods')->save(['have_det' => 1, 'is_live' => 1], ['id' => $good['id']]);
                $good->save(['have_det' => 1, 'is_live' => 1]);
                $count++;
            } else {
                $flag = false;
            }
        }
        if ($flag) {
            exit_json(1, '成功添加'.$count.'个条码，其中异常条码有'.$error_str);
        } else {
            exit_json(-1, '操作失败');
        }
    }

    /**
     * 积分商品
     */
    public function score_goods()
    {
        $list = db('gift')->where('shop_id', SHOP_ID)->select();
        $this->assign('list', $list);
        return $this->fetch();
    }

    public function gift()
    {
        $sixun = new SixunOpera();
        $list = $sixun->getGift();
        $gift = db('gift')->where('shop_id', SHOP_ID)->column('gno');
        $data = [];
        foreach ($list as $l) {
            $t = $l;
            $t['item_name'] = iconv('gbk', 'utf-8', $t['item_name']);
            if (in_array($l['vg_no'], $gift)) {
                $t['status'] = 1;
            } else {
                $t['status'] = 0;
            }
            $data[] = $t;
        }
        $this->assign('list', $data);
        return $this->fetch();
    }

    /**
     * 操作积分商品
     */
    public function oper_gift()
    {
        $goodInfo = json_decode(input('data'), true);
        $status = input('status');
        $res = false;
        if ($status == 0) {
            //删除商品
            $res = db('gift')->where(['gno' => $goodInfo['vg_no'], 'shop_id' => SHOP_ID])->delete();
        } elseif ($status == 1) {
            //添加商品
            $res = db('gift')->insert(['shop_id' => SHOP_ID, 'good_name' => $goodInfo['item_name'], 'good_price' => $goodInfo['sale_price'], 'gno' => $goodInfo['vg_no'], 'score' => $goodInfo['vg_vip_num']]);
        } else {
            exit_json(-1, '参数错误');
        }
        if ($res) {
            exit_json();
        } else {
            exit_json(-1, '操作失败');
        }
    }

    /**
     * 删除积分商品
     */
    public function score_del()
    {
        $id = input('idstr');
        $res = db('gift')->where('id', $id)->delete();
        if ($res) {
            exit_json();
        } else {
            exit_json(-1, '操作失败');
        }
    }

    /**
     * 积分商品设置图片
     */
    public function score_image()
    {
        $file = request()->file('file');
        if ($file) {
            $info = $file->move(__UPLOAD__);
            if ($info) {
                $saveName = $info->getSaveName();
                $path = "/upload/" . $saveName;
                $res = db('gift')->where('id', input('id'))->update(['image' => $path]);
                if ($res) {
                    exit_json(1, '操作成功');
                } else {
                    exit_json(-1, '操作失败');
                }
            } else {
                // 上传失败获取错误信息
                exit_json(-1, $file->getError());
            }
        } else {
            exit_json(-1, '文件不存在');
        }
    }

    public function saveRem()
    {
        $id = input('id');
        $num = input('num');
        if ($num >= 0) {
            $res = db('gift')->where('id', $id)->update(['num' => $num]);
            if ($res) {
                exit_json();
            } else {
                exit_json(-1, '保存失败');
            }
        } else {
            exit_json(-1, '数量错误');
        }

    }


}