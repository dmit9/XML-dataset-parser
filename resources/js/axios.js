import axios from 'axios';

const instance = axios.create({
    baseURL: 'localhost',
    headers: {
        'X-Requested-With': 'XMLHttpRequest'
    }
});

export default instance;
