export interface Question {
  question: string;
  question_type?: 'single' | 'multiple';
  options?: string[];
  category: string;
  subcategory: string;
  difficulty: number;
  expected_topics: string[];
  skill_type: 'hard' | 'soft';
  reasoning?: string;
}

export interface AssessmentInfo {
  id: number;
  full_name: string;
  email?: string;
  position: string;
  department?: string;
  experience_years: number;
  status: 'in_progress' | 'completed' | 'aborted';
  final_level?: string;
  total_score: number;
  max_possible_score: number;
  hard_skills_score: number;
  soft_skills_score: number;
  created_at: string;
  completed_at?: string;
}

export interface Skill {
  id?: number;
  skill_name: string;
  skill_type: 'hard' | 'soft';
  skill_category: string;
  score: number;
  max_score: number;
  level: 'beginner' | 'intermediate' | 'advanced' | 'expert';
  description: string;
  recommendations: string;
}

export interface ReportData {
  assessment_info: {
    id: number;
    full_name: string;
    position: string;
    department?: string;
    experience_years: number;
    completed_at?: string;
    created_at: string;
  };
  summary: {
    total_score: number;
    max_score: number;
    percentage: number;
    questions_answered: number;
    final_level: {
      code: string;
      name: string;
      description: string;
    };
  };
  skill_matrix: {
    hard: { average: number; skills: Skill[] };
    soft: { average: number; skills: Skill[] };
  };
  analysis: {
    strengths: Array<{ name: string; score: number; description: string }>;
    weaknesses: Array<{ name: string; score: number; recommendation: string }>;
    hard_vs_soft: {
      hard_score: number;
      soft_score: number;
      balance: 'hard_dominant' | 'soft_dominant' | 'balanced';
    };
  };
  answers_detail: Array<{
    question: string;
    category: string;
    skill_type: string;
    difficulty: number;
    answer: string;
    score: number;
    max_score: number;
    feedback: string;
    covered_topics: string[];
    missed_topics: string[];
    level_demonstrated: string;
  }>;
}

export interface DashboardStats {
  total: number;
  completed: number;
  in_progress: number;
  avg_score: number;
  unique_positions: number;
}

export interface AssessmentListItem {
  id: number;
  full_name: string;
  position: string;
  department?: string;
  status: string;
  final_level?: string;
  total_score: number;
  max_possible_score: number;
  created_at: string;
  answered_count: number;
}

export const API_BASE = '/api';

export const LEVEL_NAMES: Record<string, string> = {
  junior: 'Junior', middle: 'Middle', senior: 'Senior', lead: 'Lead'
};

export const LEVEL_COLORS: Record<string, string> = {
  junior: 'text-blue-500', middle: 'text-green-500', senior: 'text-orange-500', lead: 'text-purple-500'
};

export const LEVEL_BG_COLORS: Record<string, string> = {
  junior: 'bg-blue-50 border-blue-200',
  middle: 'bg-green-50 border-green-200',
  senior: 'bg-orange-50 border-orange-200',
  lead: 'bg-purple-50 border-purple-200'
};

export const SKILL_LEVEL_LABELS: Record<string, string> = {
  beginner: 'Начальный', intermediate: 'Средний', advanced: 'Продвинутый', expert: 'Эксперт'
};

export const SKILL_LEVEL_COLORS: Record<string, string> = {
  beginner: 'bg-red-100 text-red-700', intermediate: 'bg-yellow-100 text-yellow-700',
  advanced: 'bg-blue-100 text-blue-700', expert: 'bg-green-100 text-green-700'
};
