/*import http from 'k6/http';
import { check, sleep} from "k6";
import { randomItem } from 'https://jslib.k6.io/k6-utils/1.1.0/index.js';

export let options = {
    stages: [
        // Ramp-up from 1 to 5 VUs in 5s
        { duration: "5s", target: 5 },
        // 10 VUs for 10s
        { duration: "10s", target: 10 },
        // 50 VUs for 10s
        { duration: "10s", target: 50 },
        // Ramp-down from 50 to 100 VUs for 5s
        { duration: "5s", target: 10 },
        // Ramp-down from 5 to 0 VUs for 5s
        { duration: "5s", target: 5 }
    ]
};
export default function () {
    /*var response = http.get("http://tpmongo-php:80/", {headers: {Accepts: "application/json"}});
    check(response, { "status is 200": (r) => r.status === 200 });

    var response = http.get("http://tpmongo-php:80?page=30/", {headers: {Accepts: "application/json"}});
    check(response, { "status is 200": (r) => r.status === 200 });

    var response = http.get("http://tpmongo-php:80/", {headers: {Accepts: "application/json"}});
    check(response, { "status is 200": (r) => r.status === 200 });

    let listResp = http.get("http://tpmongo-php:80/");
    check(listResp, { "Liste livres OK": (r) => r.status === 200 });

    let page = Math.floor(Math.random() * 5) + 1; // page 1 à 5
    let pageResp = http.get(`http://tpmongo-php:80/?page=${page}`);
    check(pageResp, { "Page OK": (r) => r.status === 200 });

    let books = JSON.parse(listResp.body || "[]");
    if (books.length > 0) {
        let book = randomItem(books);
        let detailResp = http.get(`http://tpmongo-php:80/detail.php?id=${book.objectid}`);
        check(detailResp, { "Détail livre OK": (r) => r.status === 200 });
    }

    let bookToAdd = randomItem(booksToAdd);
    let addResp = http.post("http://tpmongo-php:80/create.php", JSON.stringify({
        objectid: bookToAdd.objectid,
        title: bookToAdd.title,
        author: bookToAdd.author,
        century: bookToAdd.century
    }), { headers: { "Content-Type": "application/json" } });
    check(addResp, { "Ajout livre OK": (r) => r.status === 200 || r.status === 302 });

    let deleteResp = http.del(`http://tpmongo-php:80/delete.php?id=${bookToAdd.objectid}`);
    check(deleteResp, { "Suppression livre OK": (r) => r.status === 200 || r.status === 302 });

    sleep(Math.random() * 3);


};*/

import http from 'k6/http';
import { check, sleep } from "k6";

export let options = {
    stages: [
        { duration: "5s", target: 5 },    // ramp-up 1->5 VUs
        { duration: "10s", target: 10 },  // 10 VUs
        { duration: "10s", target: 50 },  // 50 VUs
        { duration: "5s", target: 10 },   // ramp-down 50->10
        { duration: "5s", target: 0 }     // ramp-down 10->0
    ],
};

const baseURL = "http://tpmongo-php:80";

export default function () {

    // -------------------------
    // 1. Affichage de la liste
    // -------------------------
    let listRes = http.get(`${baseURL}/index.php`, { headers: { Accept: "application/json" } });
    check(listRes, { "list status is 200": (r) => r.status === 200 });
    sleep(1);

    // -------------------------
    // 2. Affichage d'une page spécifique (pagination)
    // -------------------------
    let pageNumber = Math.floor(Math.random() * 10) + 1; // page 1-10
    let pageRes = http.get(`${baseURL}/index.php?page=${pageNumber}`, { headers: { Accept: "application/json" } });
    check(pageRes, { "page status is 200": (r) => r.status === 200 });
    sleep(1);

    // -------------------------
    // 3. Consultation détails d'un livre
    // -------------------------
    let bookId = Math.floor(Math.random() * 50) + 1;
    let detailRes = http.get(`${baseURL}/get.php?objectid=${bookId}`, { headers: { Accept: "application/json" } });
    check(detailRes, { "detail status is 200": (r) => r.status === 200 });
    sleep(1);

    // -------------------------
    // 4. Ajout d'un livre
    // -------------------------
    let newBook = {
        title: `Livre Test ${Math.floor(Math.random() * 1000)}`,
        author: "Auteur Test",
        century: 21
    };
    let addRes = http.post(`${baseURL}/create.php`, newBook);
    check(addRes, { "add status is 200": (r) => r.status === 200 || r.status === 302 });
    sleep(1);

    // -------------------------
    // 5. Suppression d'un livre
    // -------------------------
    let deleteId = Math.floor(Math.random() * 50) + 1;
    let delRes = http.get(`${baseURL}/delete.php?objectid=${deleteId}`);
    check(delRes, { "delete status is 200": (r) => r.status === 200 || r.status === 302 });
    sleep(1);}