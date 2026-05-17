<script setup>

import { ref } from 'vue';

const products = ref([
    {
        name: 'Vợt Yonex Astrox 99 Pro',
        price: 4500000,
        quantity: 1,
    },
    {
        name: 'Vợt Lining Aeronaut 4000',
        price: 2000000,
        quantity: 2,
    },
    {
        name: 'Vợt Yonex Astrox 99 Pro',
        price: 4500000,
        quantity: 1,
    },
])

const formatPrice = (val) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
};

const increaseQuantity = (index) => {
    const product = products.value[index];
    if (product) {
        product.quantity++;
    }
};  

const decreaseQuantity = (index) => {
    const product = products.value[index];
    if (product && product.quantity > 1) {
        product.quantity--;
    }
};
</script>

<template>
    <div class="row mt-5">
        <div class="col-md-8">
            <h1 class="">Giỏ hàng</h1>
            <table class="table table-bordered mt-3">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Tên sản phẩm</th>
                        <th scope="col">Giá</th>
                        <th scope="col">Số lượng</th>
                        <th scope="col">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(product, index) in products" :key="product.id">
                        <th scope="row">{{ index + 1 }}</th>
                        <td>{{ product.name }}</td>
                        <td>{{ formatPrice(product.price) }}</td>
                        <td>
                            <button class="btn btn-danger me-2" @click="decreaseQuantity(index + 1)">-</button>
                            <span class="">
                                {{ product.quantity }}
                            </span>
                            <button class="btn btn-success ms-2" @click="increaseQuantity(index + 1)">+</button>
                        </td>
                        <td>
                            <button class="btn btn-danger">Xóa</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-pink-100">
                    <h1 class="">Thông tin đơn hàng</h1>
                </div>
                <div class="card-body">
                    <p>Số lượng: 3</p>
                    <p>Giá: 4.500.000 vnđ</p>
                    <hr>
                    <p>Tổng tiền: 4.500.000 vnđ</p>
                    <button class="btn btn-primary form-control">Thanh toán</button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped></style>
