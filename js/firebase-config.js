// firebase-config.js — Inicialización de Firebase
import { initializeApp } from "https://www.gstatic.com/firebasejs/12.11.0/firebase-app.js";
import { getAuth } from "https://www.gstatic.com/firebasejs/12.11.0/firebase-auth.js";
import { getFirestore } from "https://www.gstatic.com/firebasejs/12.11.0/firebase-firestore.js";

const firebaseConfig = {
  apiKey: "AIzaSyBcg17NuhDQpQ0EDQfGv9eBhtZb2XIC6mo",
  authDomain: "iso-39.firebaseapp.com",
  projectId: "iso-39",
  storageBucket: "iso-39.firebasestorage.app",
  messagingSenderId: "1087409063750",
  appId: "1:1087409063750:web:db1a86761f4e6121934747"
};

const app = initializeApp(firebaseConfig);
const auth = getAuth(app);
const db = getFirestore(app);

export { app, auth, db };
