# 📚 Modern Learning Management System (LMS) in PHP & MySQL

A comprehensive, full-featured Learning Management System built from scratch using object-oriented **PHP** and **MySQL**. This platform is designed to facilitate online education by providing a seamless, interactive experience for **Admins**, **Instructors**, and **Students**.

ℹ️ **Note:** You can find all the original images for the dashboards (Admin, Student, Instructor) and all design/process flow diagrams (ERD, Class Diagram, DB Schema, etc.) in the `/documentation` folder available in this repository.

---

## ✨ Core Features

This platform is a complete solution for online learning, featuring distinct, role-based dashboards and functionalities for every type of user.

### 👨‍💼 Admin Features
The admin has full control over the entire platform and its users.
* **User Management:** Add, delete, and manage all user accounts (Students, Instructors).
* **Course Management:** Approve, reject, or delete courses submitted by instructors.
* **Financial Oversight:** View detailed earnings and transaction reports.
* **Content Moderation:** Manage and delete course reviews.
* **Enrollment Approval:** Manually approve student enrollments.

### 🧑‍🏫 Instructor Features
Instructors have the tools to create and manage their educational content.
* **Course Creation:** A full dashboard to create, edit, and manage courses.
* **Content Upload:** Add course materials, including video lessons and quizzes.
* **Student Management:** View and track all enrolled students.
* **Interaction:** View and respond to student reviews.

### 🎓 Student Features
Students get a user-friendly interface to browse, learn, and track their progress.
* **Course Browsing:** A beautiful catalog to browse, search, and filter courses.
* **Enrollment:** Simple enrollment process with manual payment approval.
* **Learning Dashboard:** A personal dashboard to view all enrolled courses and track progress.
* **Course-Taking:** An interactive course player to watch videos, take quizzes, and view resources.
* **Feedback System:** Ability to submit course ratings and reviews after completion.
* **Certification:** Request a certificate of completion (manual generation by admin).

---

## 🛠️ Tech Stack & Tools

* **Backend:** **PHP (Object-Oriented)**
* **Database:** **MySQL**
* **Frontend:** HTML5, CSS3, JavaScript, Bootstrap
* **Design & Diagramming:** Draw.io, Canva
* **Security:** SQL Injection prevention, secure user authentication

<p align="left">
  <img src="https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP"/>
  <img src="https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL"/>
  <img src="https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white" alt="HTML5"/>
  <img src="https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white" alt="CSS3"/>
  <img src="https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black" alt="JavaScript"/>
  <img src="https://img.shields.io/badge/Bootstrap-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white" alt="Bootstrap"/>
</p>

---

## 🏛️ Project Architecture & Design

This project was built following the **VU Process Model**, combining Waterfall and Spiral models. The design is documented with industry-standard diagrams.

### Entity Relationship Diagram (ERD)
The ERD shows the logical structure of the database, including entities like `Student`, `Instructor`, `Courses`, `Enrollments`, and `Payments`.

### Database Schema
The physical database design as implemented in MySQL Workbench.

### Class Diagram
The Class Diagram illustrates the object-oriented structure of the PHP backend, showing the classes, their attributes, methods, and relationships.

### Use Case Diagram
This diagram shows the interactions between the actors (Admin, Instructor, Student, Guest) and the system's primary functions.

---

## 🚀 Getting Started

To run this project on your local machine, follow these steps:

### Prerequisites
* A web server (XAMPP, WAMP, or MAMP)
* PHP (version 7.4 or higher)
* MySQL

### Installation

1.  **Clone the repository:**
    ```bash
    git clone [https://github.com/umairXtreme/Modern-LMS-PHP.git](https://github.com/umairXtreme/Modern-LMS-PHP.git)
    ```

2.  **Move to your server directory:**
    * Place the entire project folder into your `htdocs` (for XAMPP) or `www` (for WAMP/MAMP) directory.

3.  **Import the Database:**
    * Open `phpMyAdmin`.
    * Create a new database (e.g., `bc190203051lms`).
    * Select the new database and go to the **Import** tab.
    * Upload the `bc190203051lms.sql` file (included in the repository) to create all the tables.

4.  **Configure Database Connection:**
    * Find the database connection file (e.g., `/config/config.php` or `config.php`).
    * Update the file with your local database credentials:
        ```php
        define('DB_HOST', 'localhost');
        define('DB_USER', 'root');
        define('DB_PASS', ''); // Your XAMPP password, usually empty
        define('DB_NAME', 'bc190203051lms'); // The database name you created
        ```

5.  **Run the Application:**
    * Open your web browser and navigate to `http://localhost/Modern-LMS-PHP`

---

## 🤝 Contributing

Contributions are welcome! This project is intended for educational purposes. Please fork the repository and submit a pull request with any enhancements or bug fixes.

1.  Fork the Project
2.  Create your Feature Branch (`git checkout -b feature/NewFeature`)
3.  Commit your Changes (`git commit -m 'Add some NewFeature'`)
4.  Push to the Branch (`git push origin feature/NewFeature`)
5.  Open a Pull Request

## 📜 License

This project is licensed under the **Creative Commons (CC BY-NC-SA 4.0)** license. This means you are free to share and adapt the material, but you must give appropriate credit, **may not use it for commercial purposes**, and must distribute any changes under the same license.

See the `LICENSE` file for more details.

## 🔗 Contact

**Umair Maqbool**
* **Email:** `info@cyberdevservices.com`
* **GitHub:** [github.com/umairXtreme](https://github.com/umairXtreme)
